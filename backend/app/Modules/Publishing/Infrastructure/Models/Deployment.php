<?php

declare(strict_types=1);

namespace App\Modules\Publishing\Infrastructure\Models;

use App\Modules\Shared\Domain\Support\Concerns\HasPublicUlid;
use App\Modules\Shared\Domain\Tenancy\Concerns\BelongsToWorkspace;
use App\Modules\Shared\Domain\Tenancy\Concerns\ScopedToSite;
use Database\Factories\DeploymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Build de un sitio (ADR-019). Bitácora inmutable: tras crearse sólo avanza el `status`
 * y sus campos de resultado (artifact_ref/bytes/error).
 *
 * @property int $id
 * @property string $ulid
 * @property int $site_id
 * @property string $target
 * @property string $status
 * @property string $published_hash
 * @property string|null $artifact_ref
 * @property int|null $bytes
 * @property string|null $error
 */
final class Deployment extends Model
{
    /** @use HasFactory<DeploymentFactory> */
    use BelongsToWorkspace;

    use HasFactory;
    use HasPublicUlid;
    use ScopedToSite;

    public const TARGET_STATIC = 'static';

    public const STATUS_PENDING = 'pending';

    public const STATUS_BUILDING = 'building';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'site_id',
        'target',
        'status',
        'published_hash',
        'artifact_ref',
        'bytes',
        'error',
        'triggered_by',
    ];

    protected $attributes = [
        'target' => self::TARGET_STATIC,
        'status' => self::STATUS_PENDING,
    ];

    protected function casts(): array
    {
        return [
            'bytes' => 'integer',
        ];
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_SUCCESS, self::STATUS_FAILED], true);
    }

    protected static function newFactory(): DeploymentFactory
    {
        return DeploymentFactory::new();
    }
}
