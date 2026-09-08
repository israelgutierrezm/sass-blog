<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Events;

/**
 * Se emite al crear un workspace, ya dentro del contexto del nuevo workspace.
 *
 * Efecto cruzado SOLO por evento: Identity provisiona el RBAC y Billing arranca la
 * suscripción free. Tenancy no escribe en esos módulos directamente.
 */
final class WorkspaceCreated
{
    public function __construct(
        public readonly int $workspaceId,
        public readonly int $ownerId,
    ) {}
}
