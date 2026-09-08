<?php

declare(strict_types=1);

namespace App\Modules\Builder\Policies;

use App\Models\User;
use App\Modules\Builder\Infrastructure\Models\Page;

/**
 * Autorización de páginas (RBAC). Permisos evaluados en el workspace activo
 * (teams = workspace). `publish` reservado a owner/admin (ver RoleCatalog).
 */
final class PagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('page.view');
    }

    public function view(User $user, Page $page): bool
    {
        return $user->hasPermissionTo('page.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('page.create');
    }

    public function update(User $user, Page $page): bool
    {
        return $user->hasPermissionTo('page.update');
    }

    public function publish(User $user, Page $page): bool
    {
        return $user->hasPermissionTo('page.publish');
    }
}
