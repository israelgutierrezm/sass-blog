<?php

declare(strict_types=1);

namespace App\Modules\Identity\Console;

use App\Modules\Identity\Application\RbacProvisioner;
use App\Modules\Tenancy\Infrastructure\Models\Workspace;
use Illuminate\Console\Command;

/**
 * Reprovisiona el RBAC (permisos + roles del catálogo cerrado) en TODOS los
 * workspaces existentes. Necesario cuando el catálogo gana permisos nuevos (p.ej. los
 * de Content en FASE 3): los workspaces nuevos los reciben en WorkspaceCreated, pero
 * los existentes hay que resincronizarlos con esto. Idempotente.
 */
final class ReprovisionRbacCommand extends Command
{
    protected $signature = 'identity:reprovision-rbac';

    protected $description = 'Resincroniza permisos y roles del catálogo en todos los workspaces existentes.';

    public function handle(RbacProvisioner $provisioner): int
    {
        $count = 0;

        Workspace::query()->orderBy('id')->chunk(200, function ($workspaces) use ($provisioner, &$count): void {
            foreach ($workspaces as $workspace) {
                $provisioner->forWorkspace($workspace->id);
                $count++;
            }
        });

        $this->info("RBAC reprovisionado en {$count} workspace(s).");

        return self::SUCCESS;
    }
}
