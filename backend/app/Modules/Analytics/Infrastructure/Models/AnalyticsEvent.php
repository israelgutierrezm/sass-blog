<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Infrastructure\Models;

use App\Modules\Shared\Domain\Tenancy\Concerns\BelongsToWorkspace;
use App\Modules\Shared\Domain\Tenancy\Concerns\ScopedToSite;
use Database\Factories\AnalyticsEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Evento de pageview crudo (ADR-021). Append-only, inmutable, sin PII. Interno: NO se expone
 * por API (no lleva ULID público); el admin consume sólo el rollup agregado.
 *
 * @property int $id
 * @property int $site_id
 * @property string $path
 * @property Carbon $occurred_at
 * @property string|null $referrer_host
 * @property string $visitor_hash
 * @property bool $is_bot
 */
final class AnalyticsEvent extends Model
{
    /** @use HasFactory<AnalyticsEventFactory> */
    use BelongsToWorkspace;

    use HasFactory;
    use ScopedToSite;

    /** El tiempo del evento es `occurred_at`; no hay created_at/updated_at. */
    public $timestamps = false;

    protected $fillable = [
        'site_id',
        'path',
        'occurred_at',
        'referrer_host',
        'visitor_hash',
        'is_bot',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'is_bot' => 'boolean',
        ];
    }

    protected static function newFactory(): AnalyticsEventFactory
    {
        return AnalyticsEventFactory::new();
    }
}
