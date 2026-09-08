<?php

declare(strict_types=1);

use App\Modules\Sites\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

/*
 * Sites anidados bajo el workspace. 'workspace' valida membresía y fija contexto
 * ANTES de tocar cualquier modelo scopeado.
 */
Route::middleware(['auth:sanctum', 'workspace'])->group(function (): void {
    Route::get('workspaces/{workspace}/sites', [SiteController::class, 'index'])->name('sites.index');
    Route::post('workspaces/{workspace}/sites', [SiteController::class, 'store'])->name('sites.store');
    Route::get('workspaces/{workspace}/sites/{site}', [SiteController::class, 'show'])->name('sites.show');
});
