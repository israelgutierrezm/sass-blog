<?php

declare(strict_types=1);

use App\Modules\Content\Http\Controllers\AuthorController;
use App\Modules\Content\Http\Controllers\CategoryController;
use App\Modules\Content\Http\Controllers\CollectionController;
use App\Modules\Content\Http\Controllers\EntryController;
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
        // Colecciones (schema estructural).
        Route::get('collections', [CollectionController::class, 'index'])->name('collections.index');
        Route::post('collections', [CollectionController::class, 'store'])->name('collections.store');
        Route::get('collections/{collection}', [CollectionController::class, 'show'])->name('collections.show');
        Route::patch('collections/{collection}', [CollectionController::class, 'update'])->name('collections.update');

        // Entries (borrador), anidadas bajo su colección.
        Route::get('collections/{collection}/entries', [EntryController::class, 'index'])->name('entries.index');
        Route::post('collections/{collection}/entries', [EntryController::class, 'store'])->name('entries.store');
        Route::get('collections/{collection}/entries/{entry}', [EntryController::class, 'show'])->name('entries.show');
        Route::patch('collections/{collection}/entries/{entry}', [EntryController::class, 'update'])->name('entries.update');
        Route::post('collections/{collection}/entries/{entry}/publish', [EntryController::class, 'publish'])->name('entries.publish');

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
