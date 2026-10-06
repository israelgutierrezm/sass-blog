<?php

declare(strict_types=1);

namespace App\Modules\Newsletter\Application;

use App\Modules\Newsletter\Infrastructure\Models\Subscriber;

/**
 * Confirmación del doble opt-in por `confirmation_token` (ADR-022). Búsqueda GLOBAL (endpoint
 * público sin contexto de workspace).
 *
 * Sólo un `pending` pasa a `confirmed`. Un `confirmed` es idempotente. Un `unsubscribed` NO se
 * reactiva con un enlace viejo: darse de baja tiene que ser definitivo hasta un NUEVO alta (que
 * genera otro token y otro correo). La lectura (`find`) no cambia nada: la confirmación real va
 * por POST, para que los escáneres de enlaces del correo no confirmen solos.
 */
final class ConfirmSubscription
{
    public function find(string $token): ?Subscriber
    {
        if ($token === '') {
            return null;
        }

        return Subscriber::withoutGlobalScopes()->where('confirmation_token', $token)->first();
    }

    public function handle(string $token): bool
    {
        $subscriber = $this->find($token);

        if ($subscriber === null || $subscriber->status === Subscriber::STATUS_UNSUBSCRIBED) {
            return false;
        }

        if ($subscriber->status === Subscriber::STATUS_PENDING) {
            $subscriber->status = Subscriber::STATUS_CONFIRMED;
            $subscriber->confirmed_at = now();
            $subscriber->save();
        }

        return true;
    }
}
