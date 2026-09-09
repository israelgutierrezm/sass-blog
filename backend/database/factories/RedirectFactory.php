<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Seo\Infrastructure\Models\Redirect;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Redirect>
 *
 * Requiere site_id (o ->for($site)).
 */
class RedirectFactory extends Factory
{
    protected $model = Redirect::class;

    public function definition(): array
    {
        return [
            'from_path' => '/'.fake()->unique()->slug(2),
            'to_path' => '/'.fake()->unique()->slug(2),
            'status' => 301,
            'source' => Redirect::SOURCE_MANUAL,
            'is_active' => true,
        ];
    }
}
