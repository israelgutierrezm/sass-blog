<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Content\Domain\Fields\FieldType;
use App\Modules\Content\Infrastructure\Models\CollectionField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CollectionField>
 *
 * Requiere collection_id y site_id (o ->for($collection)).
 */
class CollectionFieldFactory extends Factory
{
    protected $model = CollectionField::class;

    public function definition(): array
    {
        $key = fake()->unique()->word();

        return [
            'key' => $key,
            'label' => ucfirst($key),
            'type' => FieldType::Text->value,
            'required' => false,
            'position' => 0,
        ];
    }
}
