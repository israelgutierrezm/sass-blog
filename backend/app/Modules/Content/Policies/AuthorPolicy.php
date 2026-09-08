<?php

declare(strict_types=1);

namespace App\Modules\Content\Policies;

use App\Models\User;
use App\Modules\Content\Infrastructure\Models\Author;

/**
 * Autorización de autores (RBAC). Permiso único `author.manage` (owner/admin/editor)
 * cubre lectura y escritura; el gating por plan lo hace el middleware capability.
 */
final class AuthorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('author.manage');
    }

    public function view(User $user, Author $author): bool
    {
        return $user->hasPermissionTo('author.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('author.manage');
    }

    public function update(User $user, Author $author): bool
    {
        return $user->hasPermissionTo('author.manage');
    }

    public function delete(User $user, Author $author): bool
    {
        return $user->hasPermissionTo('author.manage');
    }
}
