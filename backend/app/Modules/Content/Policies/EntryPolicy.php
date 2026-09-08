<?php

declare(strict_types=1);

namespace App\Modules\Content\Policies;

use App\Models\User;
use App\Modules\Content\Infrastructure\Models\Entry;

/**
 * Autorización de entries (RBAC). editor crea/edita; publicar (sub-slice 8) queda
 * reservado a owner/admin. viewer sólo lee. El gating por plan lo hace el middleware
 * capability.
 */
final class EntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('entry.view');
    }

    public function view(User $user, Entry $entry): bool
    {
        return $user->hasPermissionTo('entry.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('entry.create');
    }

    public function update(User $user, Entry $entry): bool
    {
        return $user->hasPermissionTo('entry.update');
    }
}
