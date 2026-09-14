<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Analytics\Infrastructure\Models\AnalyticsEvent;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AnalyticsEvent>
 *
 * Requiere site_id (o ->for($site)).
 */
class AnalyticsEventFactory extends Factory
{
    protected $model = AnalyticsEvent::class;

    public function definition(): array
    {
        return [
            'path' => '/'.fake()->slug(),
            'occurred_at' => now(),
            'referrer_host' => null,
            'visitor_hash' => hash('sha256', (string) Str::uuid()),
            'is_bot' => false,
        ];
    }

    public function bot(): static
    {
        return $this->state(fn () => ['is_bot' => true]);
    }

    public function on(string $path): static
    {
        return $this->state(fn () => ['path' => $path]);
    }

    public function at(DateTimeInterface $when): static
    {
        return $this->state(fn () => ['occurred_at' => $when]);
    }

    public function visitor(string $hash): static
    {
        return $this->state(fn () => ['visitor_hash' => $hash]);
    }
}
