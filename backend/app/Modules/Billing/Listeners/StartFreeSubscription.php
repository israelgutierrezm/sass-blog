<?php

declare(strict_types=1);

namespace App\Modules\Billing\Listeners;

use App\Modules\Billing\Infrastructure\Models\Plan;
use App\Modules\Billing\Infrastructure\Models\Subscription;
use App\Modules\Tenancy\Events\WorkspaceCreated;

/**
 * Al crear un workspace: le arranca una suscripción al plan 'free'.
 *
 * Corre dentro del contexto del nuevo workspace (lo abre CreateWorkspace antes de
 * emitir el evento), así BelongsToWorkspace rellena workspace_id. Idempotente:
 * no duplica si ya hay suscripción.
 */
final class StartFreeSubscription
{
    public function handle(WorkspaceCreated $event): void
    {
        $free = Plan::where('key', 'free')->first();

        if ($free === null) {
            return;
        }

        Subscription::firstOrCreate(
            ['workspace_id' => $event->workspaceId],
            ['plan_id' => $free->id, 'status' => Subscription::STATUS_ACTIVE],
        );
    }
}
