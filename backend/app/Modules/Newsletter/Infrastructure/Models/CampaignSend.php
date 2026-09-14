<?php

declare(strict_types=1);

namespace App\Modules\Newsletter\Infrastructure\Models;

use App\Modules\Shared\Domain\Tenancy\Concerns\BelongsToWorkspace;
use Database\Factories\CampaignSendFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de envío por-destinatario (ADR-022). La unique (campaign_id, subscriber_id) da
 * idempotencia: re-ejecutar el job de envío salta a quien ya recibió. Interno (sin ULID): no
 * se expone por API. Se accede siempre por la campaña, así que no lleva ScopedToSite.
 *
 * @property int $id
 * @property int $campaign_id
 * @property int $subscriber_id
 * @property string $status
 */
final class CampaignSend extends Model
{
    /** @use HasFactory<CampaignSendFactory> */
    use BelongsToWorkspace;

    use HasFactory;

    protected $table = 'newsletter_campaign_sends';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'campaign_id',
        'subscriber_id',
        'status',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    protected static function newFactory(): CampaignSendFactory
    {
        return CampaignSendFactory::new();
    }
}
