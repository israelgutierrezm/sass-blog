<?php

declare(strict_types=1);

namespace App\Modules\Sites\Policies;

use App\Models\User;
use App\Modules\Sites\Infrastructure\Models\Site;

/**
 * Autorización de Sites (RBAC). Los permisos se evalúan DENTRO del workspace
 * activo (teams = workspace): el rol activo del usuario en ESE workspace decide.
 */
final class SitePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('site.view');
    }

    public function view(User $user, Site $site): bool
    {
        return $user->hasPermissionTo('site.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('site.create');
    }

    public function update(User $user, Site $site): bool
    {
        return $user->hasPermissionTo('site.update');
    }

    public function delete(User $user, Site $site): bool
    {
        return $user->hasPermissionTo('site.delete');
    }
}
