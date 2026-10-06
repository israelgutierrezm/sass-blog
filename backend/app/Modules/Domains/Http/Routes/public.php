<?php

declare(strict_types=1);

use App\Modules\Domains\Http\Controllers\PublicDomainController;
use Illuminate\Support\Facades\Route;

/*
 * Superficie PÚBLICA de dominios (ADR-020). SIN auth (frontera de confianza, ADR-006),
 * sólo-lectura y con rate-limit POR HOST (limiter `domains-public`, ver DomainsServiceProvider):
 * `resolve` lo consume el renderer (Host→sitio) y `tls-check` Caddy (on_demand_tls) antes de
 * emitir cert, ambos desde una sola IP. En producción se restringe a la red del edge por infra.
 */
Route::middleware('throttle:domains-public')->group(function (): void {
    Route::get('public/domains/resolve', [PublicDomainController::class, 'resolve'])
        ->name('public.domains.resolve');

    Route::get('public/domains/tls-check', [PublicDomainController::class, 'tlsCheck'])
        ->name('public.domains.tls-check');
});
