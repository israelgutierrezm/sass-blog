<?php

declare(strict_types=1);

use App\Modules\Builder\Http\Controllers\PublicPageController;
use Illuminate\Support\Facades\Route;

/*
 * Superficie PÚBLICA del renderer. SIN auth:sanctum ni middleware 'workspace'
 * (frontera de confianza, ADR-006). Un test estructural verifica que estas rutas
 * no llevan middleware de autenticación.
 */
Route::get('public/sites/{site}/render', [PublicPageController::class, 'render'])
    ->name('public.render');

// Preview del DRAFT por URL firmada temporal (ADR-007).
Route::get('public/sites/{site}/pages/{page}/preview', [PublicPageController::class, 'preview'])
    ->middleware('signed')
    ->name('public.preview');
