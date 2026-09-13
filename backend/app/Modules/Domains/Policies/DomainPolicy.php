<?php

declare(strict_types=1);

namespace App\Modules\Domains\Policies;

use App\Models\User;
use App\Modules\Domains\Infrastructure\Models\SiteDomain;

/**
 * Autorización de dominios (RBAC). `domain.manage` (owner/admin) cubre todo: conectar un
 * dominio es una acción de administración del sitio.
 */
final class DomainPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('domain.manage');
    }

    public function view(User $user, SiteDomain $domain): bool
    {
        return $user->hasPermissionTo('domain.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('domain.manage');
    }

    public function update(User $user, SiteDomain $domain): bool
    {
        return $user->hasPermissionTo('domain.manage');
    }

    public function delete(User $user, SiteDomain $domain): bool
    {
        return $user->hasPermissionTo('domain.manage');
    }
}
