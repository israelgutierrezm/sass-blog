<?php

declare(strict_types=1);

namespace App\Modules\Domains\Infrastructure\Models;

use App\Modules\Shared\Domain\Support\Concerns\HasPublicUlid;
use App\Modules\Shared\Domain\Tenancy\Concerns\BelongsToWorkspace;
use App\Modules\Shared\Domain\Tenancy\Concerns\ScopedToSite;
use Database\Factories\SiteDomainFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Dominio propio de un sitio (ADR-020). Enrutado (`hostname` → sitio) + máquina de estados
 * de verificación. `hostname` único global.
 *
 * @property int $id
 * @property string $ulid
 * @property int $site_id
 * @property string $hostname
 * @property string $status
 * @property string $ssl_status
 * @property string $verification_token
 * @property bool $is_primary
 */
final class SiteDomain extends Model
{
    /** @use HasFactory<SiteDomainFactory> */
    use BelongsToWorkspace;

    use HasFactory;
    use HasPublicUlid;
    use ScopedToSite;

    public const STATUS_PENDING = 'pending';

    public const STATUS_VERIFYING = 'verifying';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_FAILED = 'failed';

    public const SSL_NONE = 'none';

    public const SSL_PROVISIONING = 'provisioning';

    public const SSL_ACTIVE = 'active';

    public const SSL_FAILED = 'failed';

    protected $fillable = [
        'site_id',
        'hostname',
        'status',
        'ssl_status',
        'verification_token',
        'verified_at',
        'is_primary',
        'created_by',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
        'ssl_status' => self::SSL_NONE,
        'is_primary' => false,
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'is_primary' => 'boolean',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    protected static function newFactory(): SiteDomainFactory
    {
        return SiteDomainFactory::new();
    }
}
