<?php

declare(strict_types=1);

use App\Modules\Tenancy\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::post('workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');

    Route::get('workspaces/{workspace}', [WorkspaceController::class, 'show'])
        ->middleware('workspace')
        ->name('workspaces.show');
});
