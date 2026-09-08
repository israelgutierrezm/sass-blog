<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Content\Infrastructure\Models\Entry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Entry>
 *
 * Requiere collection_id y site_id (o ->for($collection)).
 */
class EntryFactory extends Factory
{
    protected $model = Entry::class;

    public function definition(): array
    {
        $title = rtrim(fake()->unique()->sentence(3), '.');

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 999999),
            'status' => Entry::STATUS_DRAFT,
            'data' => [],
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => Entry::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);
    }
}
