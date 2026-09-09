<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Models\User;
use App\Modules\Identity\Domain\RoleCatalog;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Provisiona el RBAC de UN workspace desde el catálogo cerrado (RoleCatalog): crea
 * los permisos globales, (re)sincroniza los roles del team con sus permisos y, si se
 * indica, asigna 'owner' al creador. Idempotente (findOrCreate/sync).
 *
 * Lo usan el listener de WorkspaceCreated (alta) y el comando de reprovisión (para
 * workspaces existentes cuando el catálogo gana permisos nuevos).
 */
final class RbacProvisioner
{
    public function __construct(private readonly PermissionRegistrar $registrar) {}

    public function forWorkspace(int $workspaceId, ?int $ownerId = null): void
    {
        $this->registrar->setPermissionsTeamId($workspaceId);

        foreach (RoleCatalog::permissions() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (RoleCatalog::ROLES as $role => $permissions) {
            Role::findOrCreate($role, 'web')->syncPermissions($permissions);
        }

        if ($ownerId !== null) {
            User::find($ownerId)?->assignRole('owner');
        }

        $this->registrar->forgetCachedPermissions();
    }
}
