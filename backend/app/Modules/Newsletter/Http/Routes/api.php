<?php

declare(strict_types=1);

use App\Modules\Newsletter\Http\Controllers\CampaignController;
use App\Modules\Newsletter\Http\Controllers\SubscriberController;
use Illuminate\Support\Facades\Route;

/*
 * API de newsletter, anidada bajo workspace + site (ADR-022). 'workspace' fija el contexto; la
 * Policy (`newsletter.manage`) autoriza. Gestionar suscriptores y campañas es de todos los planes;
 * ENVIAR una campaña lleva además 'capability:newsletter.send' (Pro).
 */
Route::middleware(['auth:sanctum', 'workspace'])
    ->prefix('workspaces/{workspace}/sites/{site}')
    ->name('newsletter.')
    ->group(function (): void {
        Route::get('newsletter/subscribers', [SubscriberController::class, 'index'])->name('subscribers.index');
        Route::delete('newsletter/subscribers/{subscriber}', [SubscriberController::class, 'destroy'])->name('subscribers.destroy');

        Route::get('newsletter/campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
        Route::post('newsletter/campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
        Route::patch('newsletter/campaigns/{campaign}', [CampaignController::class, 'update'])->name('campaigns.update');
        Route::post('newsletter/campaigns/{campaign}/send', [CampaignController::class, 'send'])
            ->middleware('capability:newsletter.send')
            ->name('campaigns.send');
    });
