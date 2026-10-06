<?php

declare(strict_types=1);

use App\Modules\Analytics\Http\Controllers\PublicAnalyticsController;
use Illuminate\Support\Facades\Route;

/*
 * Superficie PÚBLICA de analítica (ADR-006/ADR-021). SIN auth: la ingesta la llama el
 * renderer y el sitio se resuelve en el servidor. La captura es server-side, así que TODAS las
 * llamadas vienen de la IP del renderer: el limiter `analytics-collect` cuenta POR SITIO (y con
 * nombre propio, sin compartir contador con login/resolve). Superarlo sólo pierde muestras:
 * el renderer lo dispara sin esperar. En prod el endpoint se restringe a la red del edge.
 */
Route::middleware('throttle:analytics-collect')->group(function (): void {
    Route::post('public/analytics/collect', [PublicAnalyticsController::class, 'collect'])
        ->name('public.analytics.collect');
});
