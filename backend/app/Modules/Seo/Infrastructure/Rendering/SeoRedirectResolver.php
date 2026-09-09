<?php

declare(strict_types=1);

namespace App\Modules\Seo\Infrastructure\Rendering;

use App\Modules\Seo\Infrastructure\Models\Redirect;
use App\Modules\Shared\Domain\Rendering\RedirectResolver;

/**
 * Resuelve un redirect activo por (site, from_path) (ADR-018). Corre dentro del
 * WorkspaceContext del render; la consulta queda scopeada por tenant + site.
 *
 * SIGUE la cadena en el servidor (A→B→C ⇒ un único 3xx a C, mejor para SEO) con un
 * tope de saltos y detección de ciclo: si la cadena no termina (ciclo A→B→A o excede
 * el tope) NO devuelve redirect y el render cae al 404, cerrando el bucle infinito en
 * el navegador (la validación `from≠to` sólo corta el auto-loop de 1 salto).
 */
final class SeoRedirectResolver implements RedirectResolver
{
    private const MAX_HOPS = 10;

    /**
     * @return array{to: string, status: int}|null
     */
    public function resolve(int $siteId, string $path): ?array
    {
        $current = $path;
        $status = null;
        $seen = [$path => true];

        for ($hop = 0; $hop < self::MAX_HOPS; $hop++) {
            $redirect = Redirect::query()
                ->where('site_id', $siteId)
                ->where('from_path', $current)
                ->where('is_active', true)
                ->first();

            if ($redirect === null) {
                // Terminal: si hubo al menos un salto, devolvemos el destino final.
                return $status === null ? null : ['to' => $current, 'status' => $status];
            }

            // El cliente ve el status del PRIMER salto (el que originó la cadena).
            $status ??= $redirect->status;
            $current = $redirect->to_path;

            if (isset($seen[$current])) {
                return null; // ciclo: sin terminal ⇒ 404 (fail-safe anti-bucle)
            }
            $seen[$current] = true;
        }

        return null; // excedió el tope de saltos sin terminar ⇒ cadena rota ⇒ 404
    }
}
