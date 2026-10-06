<?php

declare(strict_types=1);

namespace App\Modules\Content\Policies;

use App\Models\User;
use App\Modules\Content\Infrastructure\Models\Entry;

/**
 * Autorización de entries (RBAC). editor crea/edita BORRADORES; publicar queda reservado a
 * owner/admin. viewer sólo lee. El gating por plan lo hace el middleware capability.
 *
 * Las entries son mutables (sin versionado): editar una PUBLICADA cambia el sitio en el acto, y
 * una PROGRAMADA saldrá tal cual. Por eso modificarlas exige `entry.publish`; si no, un rol sin
 * permiso de publicar alteraría contenido público sin revisión (ADR-023).
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
        if (in_array($entry->status, [Entry::STATUS_PUBLISHED, Entry::STATUS_SCHEDULED], true)) {
            return $user->hasPermissionTo('entry.publish');
        }

        return $user->hasPermissionTo('entry.update');
    }

    public function publish(User $user, Entry $entry): bool
    {
        return $user->hasPermissionTo('entry.publish');
    }
}
