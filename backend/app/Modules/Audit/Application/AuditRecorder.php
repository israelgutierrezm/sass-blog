<?php

declare(strict_types=1);

namespace App\Modules\Audit\Application;

use App\Modules\Audit\Infrastructure\Models\AuditLog;
use App\Modules\Shared\Domain\Tenancy\WorkspaceContext;
use Illuminate\Support\Facades\Auth;

/**
 * Punto único de escritura de la bitácora de auditoría.
 *
 * Toma el workspace del contexto y el actor de la sesión, para que quien registra
 * no tenga que acordarse. `audit_logs` es inmutable: sólo se escribe por aquí.
 */
final class AuditRecorder
{
    public function __construct(private readonly WorkspaceContext $context) {}

    /**
     * @param  array<string, mixed>  $attributes  site_id, entity_type, entity_id, metadata, ip
     */
    public function record(string $action, array $attributes = []): AuditLog
    {
        return AuditLog::create([
            'workspace_id' => $this->context->idOrNull(),
            'actor_id' => Auth::id(),
            'action' => $action,
            'site_id' => $attributes['site_id'] ?? null,
            'entity_type' => $attributes['entity_type'] ?? null,
            'entity_id' => $attributes['entity_id'] ?? null,
            'metadata' => $attributes['metadata'] ?? null,
            'ip' => $attributes['ip'] ?? null,
        ]);
    }
}
