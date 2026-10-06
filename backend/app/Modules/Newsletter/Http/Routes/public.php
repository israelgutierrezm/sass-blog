<?php

declare(strict_types=1);

use App\Modules\Newsletter\Http\Controllers\PublicNewsletterController;
use Illuminate\Support\Facades\Route;

/*
 * Superficie PÚBLICA de newsletter (ADR-006/ADR-022). SIN auth: el sitio se resuelve en el
 * servidor. Limiters con nombre (ver NewsletterServiceProvider):
 * - `subscribe` lo consume el formulario del sitio → por IP del visitante.
 * - `confirm` / `unsubscribe` → por TOKEN: el one-click de baja (RFC 8058) llega desde los
 *   servidores del proveedor de correo, muchas bajas legítimas por la misma IP; limitar por IP
 *   las bloquearía. El GET sólo muestra la página; la acción es el POST.
 */
Route::middleware('throttle:newsletter-subscribe')
    ->post('public/sites/{site}/newsletter/subscribe', [PublicNewsletterController::class, 'subscribe'])
    ->name('public.newsletter.subscribe');

Route::middleware('throttle:newsletter-token')->group(function (): void {
    Route::get('public/newsletter/confirm', [PublicNewsletterController::class, 'showConfirm'])
        ->name('public.newsletter.confirm');
    Route::post('public/newsletter/confirm', [PublicNewsletterController::class, 'confirm'])
        ->name('public.newsletter.confirm.submit');

    Route::get('public/newsletter/unsubscribe', [PublicNewsletterController::class, 'showUnsubscribe'])
        ->name('public.newsletter.unsubscribe');
    Route::post('public/newsletter/unsubscribe', [PublicNewsletterController::class, 'unsubscribe'])
        ->name('public.newsletter.unsubscribe.submit');
});
