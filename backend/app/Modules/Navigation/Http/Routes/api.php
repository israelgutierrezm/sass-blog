<?php

declare(strict_types=1);

use App\Modules\Navigation\Http\Controllers\MenuController;
use App\Modules\Navigation\Http\Controllers\MenuItemController;
use Illuminate\Support\Facades\Route;

/*
 * API de menús, anidada bajo el workspace + site. 'workspace' fija el contexto; la
 * Policy (menu.manage) autoriza. Sin capability: la navegación es core (ADR-017).
 */
Route::middleware(['auth:sanctum', 'workspace'])
    ->prefix('workspaces/{workspace}/sites/{site}')
    ->name('navigation.')
    ->group(function (): void {
        Route::get('menus', [MenuController::class, 'index'])->name('menus.index');
        Route::post('menus', [MenuController::class, 'store'])->name('menus.store');
        Route::get('menus/{menu}', [MenuController::class, 'show'])->name('menus.show');
        Route::patch('menus/{menu}', [MenuController::class, 'update'])->name('menus.update');
        Route::delete('menus/{menu}', [MenuController::class, 'destroy'])->name('menus.destroy');

        Route::post('menus/{menu}/items', [MenuItemController::class, 'store'])->name('items.store');
        Route::patch('menus/{menu}/items/{item}', [MenuItemController::class, 'update'])->name('items.update');
        Route::delete('menus/{menu}/items/{item}', [MenuItemController::class, 'destroy'])->name('items.destroy');
    });
