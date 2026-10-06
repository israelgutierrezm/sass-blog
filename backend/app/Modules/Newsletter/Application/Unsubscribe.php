<?php

declare(strict_types=1);

namespace App\Modules\Newsletter\Application;

use App\Modules\Newsletter\Infrastructure\Models\Subscriber;

/**
 * Baja por `unsubscribe_token` (ADR-022). Búsqueda GLOBAL (endpoint público). Idempotente. El
 * token va en cada correo de campaña y en la cabecera `List-Unsubscribe` (RFC 8058). La lectura
 * (`find`) no cambia nada: la baja real va por POST (formulario o one-click del cliente de
 * correo), para que los escáneres de enlaces no den de baja a nadie al abrir el correo.
 */
final class Unsubscribe
{
    public function find(string $token): ?Subscriber
    {
        if ($token === '') {
            return null;
        }

        return Subscriber::withoutGlobalScopes()->where('unsubscribe_token', $token)->first();
    }

    public function handle(string $token): bool
    {
        $subscriber = $this->find($token);

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
