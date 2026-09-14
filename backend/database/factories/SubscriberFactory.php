<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Newsletter\Infrastructure\Models\Subscriber;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Subscriber>
 *
 * Requiere site_id.
 */
class SubscriberFactory extends Factory
{
    protected $model = Subscriber::class;

    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'status' => Subscriber::STATUS_PENDING,
            'confirmation_token' => Str::random(64),
            'unsubscribe_token' => Str::random(64),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => ['status' => Subscriber::STATUS_CONFIRMED, 'confirmed_at' => now()]);
    }

    public function unsubscribed(): static
    {
        return $this->state(fn () => ['status' => Subscriber::STATUS_UNSUBSCRIBED, 'unsubscribed_at' => now()]);
    }
}
