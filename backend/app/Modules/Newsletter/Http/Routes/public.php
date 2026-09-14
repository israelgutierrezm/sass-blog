<?php

declare(strict_types=1);

use App\Modules\Newsletter\Http\Controllers\PublicNewsletterController;
use Illuminate\Support\Facades\Route;

/*
 * Superficie PÚBLICA de newsletter (ADR-006/ADR-022). SIN auth: el sitio se resuelve en el
 * servidor. `subscribe` lo consume el formulario del sitio (throttle por-visitante);
 * `confirm`/`unsubscribe` los abre el suscriptor desde el correo.
 */
Route::middleware('throttle:60,1')->group(function (): void {
    Route::post('public/sites/{site}/newsletter/subscribe', [PublicNewsletterController::class, 'subscribe'])
        ->name('public.newsletter.subscribe');

    Route::get('public/newsletter/confirm', [PublicNewsletterController::class, 'confirm'])
        ->name('public.newsletter.confirm');

    Route::get('public/newsletter/unsubscribe', [PublicNewsletterController::class, 'unsubscribe'])
        ->name('public.newsletter.unsubscribe');
});
