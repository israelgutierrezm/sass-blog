<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Media\Infrastructure\Models\MediaAsset;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MediaAsset>
 *
 * Requiere site_id (o ->for($site)).
 */
class MediaAssetFactory extends Factory
{
    protected $model = MediaAsset::class;

    public function definition(): array
    {
        $name = fake()->unique()->slug(2).'.jpg';

        return [
            'disk' => 'public',
            'path' => 'media/'.fake()->unique()->uuid().'.jpg',
            'original_filename' => $name,
            'mime_type' => 'image/jpeg',
            'size_bytes' => fake()->numberBetween(10_000, 2_000_000),
            'width' => 1600,
            'height' => 900,
            'checksum' => hash('sha256', Str::uuid()->toString()),
            'status' => MediaAsset::STATUS_READY,
        ];
    }

    public function processing(): static
    {
        return $this->state(fn () => ['status' => MediaAsset::STATUS_PROCESSING]);
    }
}
