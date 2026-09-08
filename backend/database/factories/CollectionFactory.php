<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Content\Domain\CollectionKind;
use App\Modules\Content\Infrastructure\Models\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Collection>
 *
 * Scopeada por workspace (contexto) y site: pasar site_id (o ->for($site)).
 */
class CollectionFactory extends Factory
{
    protected $model = Collection::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'handle' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 999999),
            'name' => Str::title($name),
            'kind' => CollectionKind::Generic->value,
        ];
    }

    public function article(): static
    {
        return $this->state(fn () => [
            'handle' => 'articles',
            'name' => 'Artículos',
            'name_singular' => 'Artículo',
            'kind' => CollectionKind::Article->value,
            'route_prefix' => 'blog',
        ]);
    }
}
