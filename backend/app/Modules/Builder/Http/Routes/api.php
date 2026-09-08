<?php

declare(strict_types=1);

use App\Modules\Builder\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

/*
 * Páginas anidadas bajo el workspace + site. 'workspace' valida la membresía y
 * fija el contexto ANTES de tocar cualquier modelo scopeado.
 */
Route::middleware(['auth:sanctum', 'workspace'])
    ->prefix('workspaces/{workspace}/sites/{site}/pages')
    ->name('pages.')
    ->group(function (): void {
        Route::get('/', [PageController::class, 'index'])->name('index');
        Route::post('/', [PageController::class, 'store'])->name('store');
        Route::get('{page}', [PageController::class, 'show'])->name('show');
        Route::patch('{page}', [PageController::class, 'update'])->name('update');
        Route::post('{page}/publish', [PageController::class, 'publish'])->name('publish');
        Route::post('{page}/preview-link', [PageController::class, 'previewLink'])->name('preview-link');
    });
