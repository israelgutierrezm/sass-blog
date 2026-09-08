<?php

declare(strict_types=1);

namespace App\Modules\Audit\Infrastructure\Models;

use App\Modules\Shared\Domain\Support\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;

/**
 * Bitácora de auditoría: INMUTABLE (sólo INSERT).
 *
 * No lleva global scope de workspace porque workspace_id es nullable (acciones de
 * plataforma). Las lecturas por workspace filtran explícitamente. La escritura la
 * hace el AuditRecorder; el modelo bloquea update/delete para que la
 * inmutabilidad no dependa de disciplina.
 *
 * @property int $id
 * @property string $ulid
 * @property int|null $workspace_id
 * @property int|null $site_id
 * @property int|null $actor_id
 * @property string $action
 * @property array<string, mixed>|null $metadata
 */
final class AuditLog extends Model
{
    use HasPublicUlid;

    public const UPDATED_AT = null;

    protected $fillable = [
        'workspace_id',
        'site_id',
        'actor_id',
        'action',
        'entity_type',
        'entity_id',
        'metadata',
        'ip',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        self::updating(function (): void {
            throw new \RuntimeException('audit_logs es inmutable: no admite UPDATE.');
        });

        self::deleting(function (): void {
            throw new \RuntimeException('audit_logs es inmutable: no admite DELETE.');
        });
    }
}
