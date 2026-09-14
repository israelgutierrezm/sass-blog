<?php

declare(strict_types=1);

namespace App\Modules\Newsletter\Application;

use App\Modules\Newsletter\Infrastructure\Models\Subscriber;

/**
 * Confirma un suscriptor por su `confirmation_token` (ADR-022). Búsqueda GLOBAL (endpoint
 * público sin contexto de workspace). Idempotente: confirmar dos veces no cambia nada.
 * Devuelve false si el token no existe.
 */
final class ConfirmSubscription
{
    public function handle(string $token): bool
    {
        if ($token === '') {
            return false;
        }

        $subscriber = Subscriber::withoutGlobalScopes()->where('confirmation_token', $token)->first();

        if ($subscriber === null) {
            return false;
        }

        if (! $subscriber->isConfirmed()) {
            $subscriber->status = Subscriber::STATUS_CONFIRMED;
            $subscriber->confirmed_at = now();
            $subscriber->unsubscribed_at = null;
            $subscriber->save();
        }

        return true;
    }
}
