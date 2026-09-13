<?php

declare(strict_types=1);

use App\Modules\Domains\Http\Controllers\PublicDomainController;
use Illuminate\Support\Facades\Route;

/*
 * Superficie PÚBLICA de dominios (ADR-020). SIN auth (frontera de confianza, ADR-006),
 * sólo-lectura y con rate-limit. `resolve` lo consume el renderer (Host→sitio); `tls-check`
 * lo consume Caddy (on_demand_tls) antes de emitir cert — en producción se restringe a la
 * red del edge por infraestructura.
 */
Route::middleware('throttle:120,1')->group(function (): void {
    Route::get('public/domains/resolve', [PublicDomainController::class, 'resolve'])
        ->name('public.domains.resolve');

    Route::get('public/domains/tls-check', [PublicDomainController::class, 'tlsCheck'])
        ->name('public.domains.tls-check');
});
