<?php

declare(strict_types=1);

namespace App\Modules\Identity\Listeners;

use App\Modules\Identity\Application\RbacProvisioner;
use App\Modules\Tenancy\Events\WorkspaceCreated;

/**
 * Al crear un workspace: provisiona sus roles (por team=workspace) con sus permisos
 * y asigna 'owner' al creador. Delega en RbacProvisioner (reutilizado por el comando
 * de reprovisión de workspaces existentes).
 */
final class ProvisionWorkspaceRbac
{
    public function __construct(private readonly RbacProvisioner $provisioner) {}

    public function handle(WorkspaceCreated $event): void
    {
        $this->provisioner->forWorkspace($event->workspaceId, $event->ownerId);
    }
}
