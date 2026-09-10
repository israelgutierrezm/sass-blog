<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Navigation\Infrastructure\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItem>
 *
 * Requiere site_id + menu_id (o ->for($menu)). Por defecto un enlace `url` (sin
 * resolución de target).
 */
class MenuItemFactory extends Factory
{
    protected $model = MenuItem::class;

    public function definition(): array
    {
        return [
            'label' => fake()->words(2, true),
            'link_type' => MenuItem::LINK_URL,
            'url' => '/'.fake()->slug(2),
            'position' => 0,
        ];
    }
}
