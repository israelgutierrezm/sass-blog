<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http\Middleware;

use App\Modules\Shared\Domain\Capabilities\Capabilities;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige que el PLAN del workspace incluya una capability (ADR-014). Corre DESPUÉS
 * de 'workspace' (necesita contexto resuelto). Si el plan no la incluye,
 * Capabilities::authorize lanza CapabilityDeniedException, que el handler global
 * mapea a 403. Los servicios repiten la verificación (defensa en profundidad).
 *
 * Uso: ->middleware('capability:cms.collections')
 */
final class EnsureCapability
{
    public function __construct(private readonly Capabilities $capabilities) {}

    public function handle(Request $request, Closure $next, string $capability): Response
    {
        $this->capabilities->authorize($capability);

        return $next($request);
    }
}
