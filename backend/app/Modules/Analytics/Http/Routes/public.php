<?php

declare(strict_types=1);

use App\Modules\Analytics\Http\Controllers\PublicAnalyticsController;
use Illuminate\Support\Facades\Route;

/*
 * Superficie PÚBLICA de analítica (ADR-006/ADR-021). SIN auth: la ingesta la llama el
 * renderer y el sitio se resuelve en el servidor. Nota: la captura es server-side, así que
 * TODAS las llamadas vienen de la IP del renderer — el throttle es un tope grueso de abuso,
 * no un límite por-visitante; en prod el endpoint se restringe a la red del edge por infra.
 */
Route::middleware('throttle:600,1')->group(function (): void {
    Route::post('public/analytics/collect', [PublicAnalyticsController::class, 'collect'])
        ->name('public.analytics.collect');
});
