<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\Models;

use App\Modules\Shared\Domain\Support\Concerns\HasPublicUlid;
use App\Modules\Shared\Domain\Tenancy\Concerns\BelongsToWorkspace;
use App\Modules\Shared\Domain\Tenancy\Concerns\ScopedToSite;
use Database\Factories\MediaAssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Asset de la librería de medios, por-sitio (ADR-016). El binario vive en
 * `disk`+`path` (Filesystem). `status` procesa -> ready al generar variantes.
 *
 * @property int $id
 * @property string $ulid
 * @property int $site_id
 * @property string $disk
 * @property string $path
 * @property string $mime_type
 * @property int $size_bytes
 * @property string $checksum
 * @property string $status
 */
final class MediaAsset extends Model
{
    /** @use HasFactory<MediaAssetFactory> */
    use BelongsToWorkspace;

    use HasFactory;
    use HasPublicUlid;
    use ScopedToSite;
    use SoftDeletes;

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    protected $fillable = [
        'site_id',
        'disk',
        'path',
        'original_filename',
        'mime_type',
        'size_bytes',
        'width',
        'height',
        'alt',
        'title',
        'checksum',
        'status',
        'created_by',
    ];

    protected $attributes = [
        'status' => self::STATUS_PROCESSING,
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    protected static function newFactory(): MediaAssetFactory
    {
        return MediaAssetFactory::new();
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /** @return HasMany<MediaVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(MediaVariant::class);
    }
}
