<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Newsletter\Infrastructure\Models\Campaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 *
 * Requiere site_id.
 */
class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    public function definition(): array
    {
        return [
            'subject' => fake()->sentence(),
            'body' => '<p>'.fake()->paragraph().'</p>',
            'status' => Campaign::STATUS_DRAFT,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => ['status' => Campaign::STATUS_SENT, 'sent_at' => now()]);
    }
}
