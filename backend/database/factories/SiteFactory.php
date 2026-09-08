<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Sites\Infrastructure\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Site>
 *
 * Site es scopeado por workspace: si no se pasa workspace_id, el trait
 * BelongsToWorkspace lo toma del contexto activo. En pruebas: fijar el contexto
 * (actingForWorkspace) o usar ->for($workspace).
 */
class SiteFactory extends Factory
{
    protected $model = Site::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'status' => Site::STATUS_DRAFT,
            'settings' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => Site::STATUS_PUBLISHED]);
    }
}
