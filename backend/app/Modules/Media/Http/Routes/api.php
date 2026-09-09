<?php

declare(strict_types=1);

use App\Modules\Media\Http\Controllers\MediaController;
use Illuminate\Support\Facades\Route;

/*
 * API de la librería de medios, anidada bajo el workspace + site. 'workspace' fija
 * el contexto; 'capability:media.library' hace el gating por plan (ADR-014/016).
 */
Route::middleware(['auth:sanctum', 'workspace', 'capability:media.library'])
    ->prefix('workspaces/{workspace}/sites/{site}')
    ->name('media.')
    ->group(function (): void {
        Route::get('media', [MediaController::class, 'index'])->name('index');
        Route::post('media', [MediaController::class, 'store'])->name('store');
        Route::get('media/{asset}', [MediaController::class, 'show'])->name('show');
        Route::patch('media/{asset}', [MediaController::class, 'update'])->name('update');
        Route::delete('media/{asset}', [MediaController::class, 'destroy'])->name('destroy');
    });
