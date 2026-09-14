<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Analytics\Infrastructure\Models\AnalyticsDailyStat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnalyticsDailyStat>
 *
 * Requiere site_id (o ->for($site)).
 */
class AnalyticsDailyStatFactory extends Factory
{
    protected $model = AnalyticsDailyStat::class;

    public function definition(): array
    {
        return [
            'stat_date' => today(),
            'path' => '/',
            'views' => fake()->numberBetween(1, 100),
            'visitors' => fake()->numberBetween(1, 50),
        ];
    }
}
