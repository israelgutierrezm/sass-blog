<?php

declare(strict_types=1);

namespace App\Modules\Content\Policies;

use App\Models\User;
use App\Modules\Content\Infrastructure\Models\Collection;

/**
 * Autorización de colecciones (RBAC). El schema es estructural: sólo owner/admin lo
 * crean/editan (editor no); todos los roles con acceso pueden verlo. El gating por
 * plan lo hace el middleware capability.
 */
final class CollectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('collection.view');
    }

    public function view(User $user, Collection $collection): bool
    {
        return $user->hasPermissionTo('collection.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('collection.create');
    }

    public function update(User $user, Collection $collection): bool
    {
        return $user->hasPermissionTo('collection.update');
    }
}
