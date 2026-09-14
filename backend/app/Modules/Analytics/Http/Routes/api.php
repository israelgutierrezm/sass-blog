<?php

declare(strict_types=1);

use App\Modules\Analytics\Http\Controllers\AnalyticsController;
use Illuminate\Support\Facades\Route;

/*
 * API del dashboard de analítica, anidada bajo workspace + site (ADR-021). 'workspace' fija
 * el contexto; la Policy (`analytics.view`) autoriza. `summary` es para todos los planes (la
 * analítica básica); `export` (CSV) lleva además 'capability:analytics.advanced' (Pro).
 */
Route::middleware(['auth:sanctum', 'workspace'])
    ->prefix('workspaces/{workspace}/sites/{site}')
    ->name('analytics.')
    ->group(function (): void {
        Route::get('analytics/summary', [AnalyticsController::class, 'summary'])->name('summary');
        Route::get('analytics/export', [AnalyticsController::class, 'export'])
            ->middleware('capability:analytics.advanced')
            ->name('export');
    });
