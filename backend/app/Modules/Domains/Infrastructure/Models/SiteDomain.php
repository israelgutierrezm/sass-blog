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
 * Reclamación de un dominio propio por un sitio (ADR-020 + ADR-025). Enrutado (`hostname` →
 * sitio) + máquina de estados de verificación. Activar exige PROPIEDAD (TXT con el token de esta
 * reclamación) y enrutado (CNAME/A al ingress). Puede haber varias reclamaciones del mismo
 * hostname, pero sólo UNA activa (columna generada `active_hostname`, UNIQUE).
 *
 * @property int $id
 * @property string $ulid
 * @property int $site_id
 * @property string $hostname
 * @property string|null $active_hostname
 * @property string $status
 * @property string|null $failure_reason
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

    /** Falta el TXT `_sassblog-verify` con el token de esta reclamación (ADR-025). */
    public const FAILURE_OWNERSHIP = 'ownership';

    /** El hostname no apunta (CNAME/A) al ingress. */
    public const FAILURE_ROUTING = 'routing';

    /** El hostname ya está activo en otro sitio: hay que desconectarlo allí primero. */
    public const FAILURE_TAKEN = 'taken';

    /** Etiqueta del registro TXT de propiedad: `_sassblog-verify.{hostname}`. */
    public const CHALLENGE_LABEL = '_sassblog-verify';

    public const SSL_NONE = 'none';

    public const SSL_PROVISIONING = 'provisioning';

    public const SSL_ACTIVE = 'active';

    public const SSL_FAILED = 'failed';

    protected $fillable = [
        'site_id',
        'hostname',
        'status',
        'failure_reason',
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

    /** Nombre del registro TXT que prueba la propiedad del dominio. */
    public function challengeName(): string
    {
        return self::CHALLENGE_LABEL.'.'.$this->hostname;
    }

    /** Valor esperado del TXT: lleva el token de ESTA reclamación (otro tenant tiene otro). */
    public function challengeValue(): string
    {
        return 'sassblog-verify='.$this->verification_token;
    }

    protected static function newFactory(): SiteDomainFactory
    {
        return SiteDomainFactory::new();
    }
}
