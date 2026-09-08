<?php

declare(strict_types=1);

namespace App\Modules\Builder\Infrastructure\Models;

use App\Modules\Shared\Domain\Support\Concerns\HasPublicUlid;
use App\Modules\Shared\Domain\Tenancy\Concerns\BelongsToWorkspace;
use Database\Factories\PageVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Instantánea del page schema. El draft es mutable; la versión PUBLICADA es
 * inmutable. Scopeada por workspace_id (y site_id). Append-only (sin softDeletes).
 *
 * La guarda de inmutabilidad es CONDICIONAL al estado ORIGINAL persistido: bloquea
 * UPDATE/DELETE sólo si la fila YA estaba publicada, permitiendo así la transición
 * draft -> published (en ese UPDATE el original sigue siendo 'draft').
 *
 * @property int $id
 * @property string $ulid
 * @property int $workspace_id
 * @property int $site_id
 * @property int $page_id
 * @property int $version_number
 * @property string $status
 * @property int $schema_version
 * @property array<string, mixed> $schema
 */
final class PageVersion extends Model
{
    /** @use HasFactory<PageVersionFactory> */
    use BelongsToWorkspace;

    use HasFactory;
    use HasPublicUlid;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'site_id',
        'page_id',
        'version_number',
        'status',
        'schema_version',
        'schema',
        'label',
        'created_by',
        'published_at',
        'published_by',
    ];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'schema_version' => 1,
    ];

    protected function casts(): array
    {
        return [
            'schema' => 'array',
            'published_at' => 'datetime',
        ];
    }

    protected static function newFactory(): PageVersionFactory
    {
        return PageVersionFactory::new();
    }

    protected static function booted(): void
    {
        self::updating(function (PageVersion $version): void {
            if ($version->getOriginal('status') === self::STATUS_PUBLISHED) {
                throw new RuntimeException('Una page_version publicada es inmutable: no admite UPDATE.');
            }
        });

        self::deleting(function (PageVersion $version): void {
            if ($version->getOriginal('status') === self::STATUS_PUBLISHED) {
                throw new RuntimeException('Una page_version publicada es inmutable: no admite DELETE.');
            }
        });
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
