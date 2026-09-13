<?php

declare(strict_types=1);

use App\Modules\Publishing\Http\Controllers\DeploymentController;
use Illuminate\Support\Facades\Route;

/*
 * API de deployments (export estático), anidada bajo el workspace + site. 'workspace' fija
 * el contexto; 'capability:site.export.static' hace el gating por plan (ADR-019); la Policy
 * (site.publish) autoriza.
 */
Route::middleware(['auth:sanctum', 'workspace', 'capability:site.export.static'])
    ->prefix('workspaces/{workspace}/sites/{site}')
    ->name('publishing.deployments.')
    ->group(function (): void {
        Route::get('deployments', [DeploymentController::class, 'index'])->name('index');
        Route::post('deployments', [DeploymentController::class, 'store'])->name('store');
        Route::get('deployments/{deployment}', [DeploymentController::class, 'show'])->name('show');
        Route::get('deployments/{deployment}/download', [DeploymentController::class, 'download'])->name('download');
    });
