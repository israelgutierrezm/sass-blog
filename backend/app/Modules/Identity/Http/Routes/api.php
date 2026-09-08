<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

/*
 * Rutas de Identity. El prefijo (api/v1) y el nombre (api.v1.) los aplica
 * ModuleServiceProvider; aquí sólo el camino relativo.
 */

Route::post('register', [AuthController::class, 'register'])
    ->middleware('throttle:10,1')->name('auth.register');

Route::post('login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1')->name('auth.login');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('me', [AuthController::class, 'me'])->name('auth.me');
});
