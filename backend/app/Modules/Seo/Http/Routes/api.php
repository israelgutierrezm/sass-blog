<?php

declare(strict_types=1);

use App\Modules\Seo\Http\Controllers\RedirectController;
use Illuminate\Support\Facades\Route;

/*
 * API de redirects, anidada bajo el workspace + site. 'workspace' fija el contexto;
 * la Policy (redirect.manage) autoriza. Sin capability: es higiene SEO básica, no una
 * feature de plan (ADR-018).
 */
Route::middleware(['auth:sanctum', 'workspace'])
    ->prefix('workspaces/{workspace}/sites/{site}')
    ->name('seo.redirects.')
    ->group(function (): void {
        Route::get('redirects', [RedirectController::class, 'index'])->name('index');
        Route::post('redirects', [RedirectController::class, 'store'])->name('store');
        Route::patch('redirects/{redirect}', [RedirectController::class, 'update'])->name('update');
        Route::delete('redirects/{redirect}', [RedirectController::class, 'destroy'])->name('destroy');
    });
