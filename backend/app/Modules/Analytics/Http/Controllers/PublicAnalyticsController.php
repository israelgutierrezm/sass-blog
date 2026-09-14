<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Analytics\Application\RecordPageView;
use App\Modules\Analytics\Http\Requests\CollectAnalyticsRequest;
use App\Modules\Sites\Infrastructure\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Ingesta PÚBLICA de pageviews (ADR-006/ADR-021). SIN auth: el workspace se deriva del sitio
 * en el servidor. Lo llama el renderer server-to-server tras un render exitoso, reenviando la
 * IP y el User-Agent del visitante (X-Visitor-Ip / User-Agent). En prod se restringe a la red
 * del edge por infraestructura. Responde 204 SIEMPRE (beacon): ni filtra si el sitio existe ni
 * puede romper el render del cliente.
 */
final class PublicAnalyticsController extends Controller
{
    public function collect(CollectAnalyticsRequest $request, RecordPageView $recorder): Response
    {
        $site = Site::withoutGlobalScopes()
            ->where('ulid', Str::upper((string) $request->input('site')))
            ->where('status', '!=', Site::STATUS_ARCHIVED)
            ->first();

        if ($site !== null) {
            $recorder->handle(
                $site,
                (string) $request->input('path'),
                $request->input('referrer'),
                $this->visitorIp($request),
                (string) $request->userAgent(),
            );
        }

        return response()->noContent();
    }

    /** IP del VISITANTE reenviada por el renderer; si falta, la de la conexión. */
    private function visitorIp(Request $request): string
    {
        $forwarded = trim((string) $request->header('X-Visitor-Ip', ''));

        return $forwarded !== '' ? $forwarded : (string) $request->ip();
    }
}
