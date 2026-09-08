<?php

declare(strict_types=1);

namespace App\Modules\Identity\Listeners;

use App\Models\User;
use App\Modules\Identity\Domain\RoleCatalog;
use App\Modules\Tenancy\Events\WorkspaceCreated;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Al crear un workspace: provisiona sus roles (por team=workspace) con sus
 * permisos y asigna 'owner' al creador.
 *
 * Los permisos son globales (catálogo cerrado); los roles se crean por workspace
 * para que el team de Spatie los aísle. Es idempotente (findOrCreate/sync).
 */
final class ProvisionWorkspaceRbac
{
    public function handle(WorkspaceCreated $event): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($event->workspaceId);

        foreach (RoleCatalog::permissions() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (RoleCatalog::ROLES as $role => $permissions) {
            Role::findOrCreate($role, 'web')->syncPermissions($permissions);
        }

        User::find($event->ownerId)?->assignRole('owner');

        $registrar->forgetCachedPermissions();
    }
}
