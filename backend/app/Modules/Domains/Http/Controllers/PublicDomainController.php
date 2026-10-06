<?php

declare(strict_types=1);

namespace App\Modules\Domains\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Domains\Infrastructure\Models\SiteDomain;
use App\Modules\Sites\Infrastructure\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Superficie PÚBLICA de dominios (SIN auth, sólo-lectura, ADR-020). Un hostname ACTIVO es único
 * en la plataforma (`active_hostname`, ADR-025), así que la búsqueda es global (sin
 * WorkspaceContext, withoutGlobalScopes). Sólo los dominios `active` resuelven / pasan el
 * tls-check — nunca las reclamaciones pendientes o fallidas.
 */
final class PublicDomainController extends Controller
{
    /**
     * host → sitio, para que el renderer resuelva el sitio por el header Host.
     */
    public function resolve(Request $request): JsonResponse
    {
        $domain = $this->activeByHostname((string) $request->query('host', ''));
        abort_if($domain === null, 404, 'Dominio no encontrado.');

        $site = Site::withoutGlobalScopes()->find($domain->site_id);
        abort_if($site === null, 404, 'Sitio no encontrado.');

        return response()
            ->json(['data' => ['site' => $site->ulid]])
            ->header('Cache-Control', 'public, max-age=60');
    }

    /**
     * Ask-endpoint de Caddy (`on_demand_tls`): 200 SÓLO si el dominio está verificado
     * (active); si no, 404 → Caddy NO emite certificado. Cierra el abuso de emisión.
     */
    public function tlsCheck(Request $request): Response
    {
        $domain = $this->activeByHostname((string) $request->query('domain', ''));
        abort_if($domain === null, 404, 'Dominio no autorizado.');

        return response('', 200);
    }

    private function activeByHostname(string $raw): ?SiteDomain
    {
        $host = explode(':', strtolower(trim($raw)))[0]; // sin puerto
        $host = rtrim($host, '.');
        if ($host === '') {
            return null;
        }

        // `active_hostname` sólo tiene valor si status = active (columna generada, UNIQUE).
        return SiteDomain::withoutGlobalScopes()->where('active_hostname', $host)->first();
    }
}
