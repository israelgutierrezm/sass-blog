<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Identity\Domain\RoleCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Siembra el catálogo global de permisos (cerrado). Los ROLES no se siembran aquí:
 * se crean por workspace al provisionarlo (ProvisionWorkspaceRbac), porque el team
 * de Spatie es el workspace.
 */
class RbacSeeder extends Seeder
{
    public function run(): void
    {
        // Permisos globales: no dependen de team.
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        foreach (RoleCatalog::permissions() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
