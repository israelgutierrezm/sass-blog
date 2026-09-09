<?php

declare(strict_types=1);

namespace App\Modules\Media\Policies;

use App\Models\User;
use App\Modules\Media\Infrastructure\Models\MediaAsset;

/**
 * Autorización de la librería de medios (RBAC). `media.view` (todos los roles con
 * acceso) lee; `media.manage` (owner/admin/editor) sube/edita/elimina. El gating por
 * plan lo hace el middleware capability (media.library).
 */
final class MediaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('media.view');
    }

    public function view(User $user, MediaAsset $asset): bool
    {
        return $user->hasPermissionTo('media.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('media.manage');
    }

    public function update(User $user, MediaAsset $asset): bool
    {
        return $user->hasPermissionTo('media.manage');
    }

    public function delete(User $user, MediaAsset $asset): bool
    {
        return $user->hasPermissionTo('media.manage');
    }
}
