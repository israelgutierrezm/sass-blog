<?php

declare(strict_types=1);

use App\Modules\Content\Http\Controllers\AuthorController;
use App\Modules\Content\Http\Controllers\CategoryController;
use Illuminate\Support\Facades\Route;

/*
 * API admin del CMS, anidada bajo el workspace + site. 'workspace' valida la
 * membresía y fija el contexto; 'capability:cms.collections' hace el gating por
 * plan (ADR-014). Las categorías cuelgan de su colección (collection_id intrínseco).
 */
Route::middleware(['auth:sanctum', 'workspace', 'capability:cms.collections'])
    ->prefix('workspaces/{workspace}/sites/{site}')
    ->name('content.')
    ->group(function (): void {
        // Autores (nivel site).
        Route::get('authors', [AuthorController::class, 'index'])->name('authors.index');
        Route::post('authors', [AuthorController::class, 'store'])->name('authors.store');
        Route::get('authors/{author}', [AuthorController::class, 'show'])->name('authors.show');
        Route::patch('authors/{author}', [AuthorController::class, 'update'])->name('authors.update');
        Route::delete('authors/{author}', [AuthorController::class, 'destroy'])->name('authors.destroy');

        // Categorías (anidadas bajo colección).
        Route::get('collections/{collection}/categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::post('collections/{collection}/categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::get('collections/{collection}/categories/{category}', [CategoryController::class, 'show'])->name('categories.show');
        Route::patch('collections/{collection}/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('collections/{collection}/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
    });
