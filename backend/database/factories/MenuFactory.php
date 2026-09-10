<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Navigation\Infrastructure\Models\Menu;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Menu>
 *
 * Requiere site_id (o ->for($site)).
 */
class MenuFactory extends Factory
{
    protected $model = Menu::class;

    public function definition(): array
    {
        return [
            'handle' => fake()->unique()->slug(1),
            'name' => fake()->words(2, true),
        ];
    }
}
