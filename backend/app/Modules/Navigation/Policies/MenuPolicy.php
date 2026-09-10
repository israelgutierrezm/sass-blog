<?php

declare(strict_types=1);

namespace App\Modules\Navigation\Policies;

use App\Models\User;
use App\Modules\Navigation\Infrastructure\Models\Menu;

/**
 * Autorización de menús (RBAC). `menu.manage` (owner/admin/editor) cubre ver y editar:
 * la navegación es una herramienta editorial del sitio.
 */
final class MenuPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('menu.manage');
    }

    public function view(User $user, Menu $menu): bool
    {
        return $user->hasPermissionTo('menu.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('menu.manage');
    }

    public function update(User $user, Menu $menu): bool
    {
        return $user->hasPermissionTo('menu.manage');
    }

    public function delete(User $user, Menu $menu): bool
    {
        return $user->hasPermissionTo('menu.manage');
    }
}
