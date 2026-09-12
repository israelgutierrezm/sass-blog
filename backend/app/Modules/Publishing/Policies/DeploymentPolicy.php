<?php

declare(strict_types=1);

namespace App\Modules\Publishing\Policies;

use App\Models\User;
use App\Modules\Publishing\Infrastructure\Models\Deployment;

/**
 * Autorización de deployments (RBAC). `site.publish` (owner/admin) cubre disparar y ver:
 * publicar/exportar un sitio es una acción de administración del sitio, no de edición.
 */
final class DeploymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('site.publish');
    }

    public function view(User $user, Deployment $deployment): bool
    {
        return $user->hasPermissionTo('site.publish');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('site.publish');
    }
}
