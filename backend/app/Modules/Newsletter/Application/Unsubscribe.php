<?php

declare(strict_types=1);

namespace App\Modules\Newsletter\Application;

use App\Modules\Newsletter\Infrastructure\Models\Subscriber;

/**
 * Baja de un suscriptor por su `unsubscribe_token` (ADR-022). Búsqueda GLOBAL (endpoint público).
 * Idempotente. El token va en cada correo de campaña. Devuelve false si el token no existe.
 */
final class Unsubscribe
{
    public function handle(string $token): bool
    {
        if ($token === '') {
            return false;
        }

        $subscriber = Subscriber::withoutGlobalScopes()->where('unsubscribe_token', $token)->first();

        if ($subscriber === null) {
            return false;
        }

        if ($subscriber->status !== Subscriber::STATUS_UNSUBSCRIBED) {
            $subscriber->status = Subscriber::STATUS_UNSUBSCRIBED;
            $subscriber->unsubscribed_at = now();
            $subscriber->save();
        }

        return true;
    }
}
