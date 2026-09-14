<?php

declare(strict_types=1);

namespace App\Modules\Newsletter\Infrastructure\Models;

use App\Modules\Shared\Domain\Support\Concerns\HasPublicUlid;
use App\Modules\Shared\Domain\Tenancy\Concerns\BelongsToWorkspace;
use App\Modules\Shared\Domain\Tenancy\Concerns\ScopedToSite;
use Database\Factories\CampaignFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Campaña de newsletter de un sitio (ADR-022): un envío con asunto + cuerpo, máquina de estados
 * draft → sending → sent (o failed) y contadores del envío.
 *
 * @property int $id
 * @property string $ulid
 * @property int $site_id
 * @property string $subject
 * @property string $body
 * @property string $status
 * @property int $recipients_count
 * @property int $sent_count
 * @property int $failed_count
 */
final class Campaign extends Model
{
    /** @use HasFactory<CampaignFactory> */
    use BelongsToWorkspace;

    use HasFactory;
    use HasPublicUlid;
    use ScopedToSite;

    protected $table = 'newsletter_campaigns';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'site_id',
        'subject',
        'body',
        'status',
        'recipients_count',
        'sent_count',
        'failed_count',
        'sent_at',
        'created_by',
    ];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'recipients_count' => 0,
        'sent_count' => 0,
        'failed_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'recipients_count' => 'integer',
            'sent_count' => 'integer',
            'failed_count' => 'integer',
        ];
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    protected static function newFactory(): CampaignFactory
    {
        return CampaignFactory::new();
    }
}
