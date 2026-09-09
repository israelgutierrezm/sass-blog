<?php

declare(strict_types=1);

namespace App\Modules\Seo\Policies;

use App\Models\User;
use App\Modules\Seo\Infrastructure\Models\Redirect;

/**
 * Autorización de redirects (RBAC). `redirect.manage` (owner/admin) cubre todo; los
 * redirects son higiene de SEO estructural, no edición de contenido.
 */
final class RedirectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('redirect.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('redirect.manage');
    }

    public function update(User $user, Redirect $redirect): bool
    {
        return $user->hasPermissionTo('redirect.manage');
    }

    public function delete(User $user, Redirect $redirect): bool
    {
        return $user->hasPermissionTo('redirect.manage');
    }
}
