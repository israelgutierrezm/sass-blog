<?php

declare(strict_types=1);

namespace App\Modules\Content\Policies;

use App\Models\User;
use App\Modules\Content\Infrastructure\Models\Category;

/**
 * Autorización de categorías (RBAC). Permiso único `category.manage`
 * (owner/admin/editor); el gating por plan lo hace el middleware capability.
 */
final class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('category.manage');
    }

    public function view(User $user, Category $category): bool
    {
        return $user->hasPermissionTo('category.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('category.manage');
    }

    public function update(User $user, Category $category): bool
    {
        return $user->hasPermissionTo('category.manage');
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->hasPermissionTo('category.manage');
    }
}
