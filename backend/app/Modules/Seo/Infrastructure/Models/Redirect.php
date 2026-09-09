<?php

declare(strict_types=1);

namespace App\Modules\Seo\Infrastructure\Models;

use App\Modules\Shared\Domain\Support\Concerns\HasPublicUlid;
use App\Modules\Shared\Domain\Tenancy\Concerns\BelongsToWorkspace;
use App\Modules\Shared\Domain\Tenancy\Concerns\ScopedToSite;
use Database\Factories\RedirectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Redirect por-sitio (ADR-018): from_path -> to_path con un código 3xx. `source`
 * distingue los manuales de los auto-creados al cambiar un slug.
 *
 * @property int $id
 * @property string $ulid
 * @property int $site_id
 * @property string $from_path
 * @property string $to_path
 * @property int $status
 * @property string $source
 * @property bool $is_active
 */
final class Redirect extends Model
{
    /** @use HasFactory<RedirectFactory> */
    use BelongsToWorkspace;

    use HasFactory;
    use HasPublicUlid;
    use ScopedToSite;

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_SLUG_CHANGE = 'slug_change';

    protected $fillable = [
        'site_id',
        'from_path',
        'to_path',
        'status',
        'source',
        'is_active',
        'created_by',
    ];

    protected $attributes = [
        'status' => 301,
        'source' => self::SOURCE_MANUAL,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'status' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): RedirectFactory
    {
        return RedirectFactory::new();
    }
}
