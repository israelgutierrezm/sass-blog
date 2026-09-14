<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Policies;

use App\Models\User;

/**
 * Autorización de analítica (RBAC, ADR-021). Sólo lectura: `analytics.view`
 * (owner/admin/editor). El gating por PLAN (rango libre, referrers, export) lo hace la
 * capability `analytics.advanced` en el controlador, no la Policy.
 */
final class AnalyticsPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('analytics.view');
    }
}
