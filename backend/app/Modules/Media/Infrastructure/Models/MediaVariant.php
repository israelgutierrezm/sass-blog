<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\Models;

use App\Modules\Shared\Domain\Tenancy\Concerns\BelongsToWorkspace;
use App\Modules\Shared\Domain\Tenancy\Concerns\ScopedToSite;
use Database\Factories\MediaVariantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Derivado (thumb/medium/large) de un asset de imagen (ADR-016). Hijo del asset;
 * scopeado por workspace + site como el resto del dominio. Sin ULID (interno).
 *
 * @property int $id
 * @property int $media_asset_id
 * @property string $variant
 * @property string $disk
 * @property string $path
 * @property int $width
 * @property int $height
 */
final class MediaVariant extends Model
{
    /** @use HasFactory<MediaVariantFactory> */
    use BelongsToWorkspace;

    use HasFactory;
    use ScopedToSite;

    public const VARIANT_THUMB = 'thumb';

    public const VARIANT_MEDIUM = 'medium';

    public const VARIANT_LARGE = 'large';

    protected $fillable = [
        'site_id',
        'media_asset_id',
        'variant',
        'disk',
        'path',
        'width',
        'height',
    ];

    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    protected static function newFactory(): MediaVariantFactory
    {
        return MediaVariantFactory::new();
    }

    /** @return BelongsTo<MediaAsset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }
}
