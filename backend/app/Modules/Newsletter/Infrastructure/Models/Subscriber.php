<?php

declare(strict_types=1);

namespace App\Modules\Newsletter\Infrastructure\Models;

use App\Modules\Shared\Domain\Support\Concerns\HasPublicUlid;
use App\Modules\Shared\Domain\Tenancy\Concerns\BelongsToWorkspace;
use App\Modules\Shared\Domain\Tenancy\Concerns\ScopedToSite;
use Database\Factories\SubscriberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Suscriptor de newsletter de un sitio (ADR-022). Doble opt-in: pending → confirmed →
 * unsubscribed. Email único por sitio; tokens únicos globales (los resuelven endpoints públicos).
 *
 * @property int $id
 * @property string $ulid
 * @property int $site_id
 * @property string $email
 * @property string $status
 * @property string $confirmation_token
 * @property string $unsubscribe_token
 */
final class Subscriber extends Model
{
    /** @use HasFactory<SubscriberFactory> */
    use BelongsToWorkspace;

    use HasFactory;
    use HasPublicUlid;
    use ScopedToSite;

    protected $table = 'newsletter_subscribers';

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_UNSUBSCRIBED = 'unsubscribed';

    protected $fillable = [
        'site_id',
        'email',
        'status',
        'confirmation_token',
        'unsubscribe_token',
        'confirmed_at',
        'unsubscribed_at',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    protected static function newFactory(): SubscriberFactory
    {
        return SubscriberFactory::new();
    }
}
