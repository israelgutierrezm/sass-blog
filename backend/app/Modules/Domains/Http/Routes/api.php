<?php

declare(strict_types=1);

use App\Modules\Domains\Http\Controllers\DomainController;
use Illuminate\Support\Facades\Route;

/*
 * API de dominios propios, anidada bajo el workspace + site. 'workspace' fija el contexto;
 * 'capability:site.custom_domain' hace el gating por plan (ADR-020); la Policy
 * (domain.manage) autoriza.
 */
Route::middleware(['auth:sanctum', 'workspace', 'capability:site.custom_domain'])
    ->prefix('workspaces/{workspace}/sites/{site}')
    ->name('domains.')
    ->group(function (): void {
        Route::get('domains', [DomainController::class, 'index'])->name('index');
        Route::post('domains', [DomainController::class, 'store'])->name('store');
        Route::post('domains/{domain}/recheck', [DomainController::class, 'recheck'])->name('recheck');
        Route::post('domains/{domain}/primary', [DomainController::class, 'setPrimary'])->name('primary');
        Route::delete('domains/{domain}', [DomainController::class, 'destroy'])->name('destroy');
    });
