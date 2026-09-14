<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Newsletter\Infrastructure\Models\CampaignSend;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignSend>
 *
 * Requiere campaign_id + subscriber_id.
 */
class CampaignSendFactory extends Factory
{
    protected $model = CampaignSend::class;

    public function definition(): array
    {
        return [
            'status' => CampaignSend::STATUS_SENT,
            'sent_at' => now(),
        ];
    }
}
