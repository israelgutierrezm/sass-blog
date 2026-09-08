<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application;

use App\Modules\Billing\Infrastructure\Models\Plan;
use App\Modules\Billing\Infrastructure\Models\Subscription;
use App\Modules\Shared\Domain\Capabilities\CapabilityResolver;

/**
 * Implementación de CapabilityResolver: resuelve las capabilities de un workspace
 * siguiendo subscription → plan → plan_capabilities.
 *
 * Consulta con `withoutGlobalScopes` y filtro explícito por workspace_id para no
 * acoplarse al contexto activo (se puede resolver un workspace distinto del actual
 * sin fugas: el where es explícito).
 */
final class PlanCapabilityResolver implements CapabilityResolver
{
    public function grantsFor(int $workspaceId): array
    {
        $subscription = Subscription::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->first();

        if ($subscription === null) {
            return [];
        }

        $plan = Plan::with('capabilities')->find($subscription->plan_id);

        if ($plan === null) {
            return [];
        }

        $grants = [];

        foreach ($plan->capabilities as $capability) {
            /** @var int|null $limit */
            $limit = $capability->pivot->limit;
            $grants[$capability->key] = $limit;
        }

        return $grants;
    }
}
