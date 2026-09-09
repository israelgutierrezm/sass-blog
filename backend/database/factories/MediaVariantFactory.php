<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Media\Infrastructure\Models\MediaVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MediaVariant>
 *
 * Requiere site_id y media_asset_id.
 */
class MediaVariantFactory extends Factory
{
    protected $model = MediaVariant::class;

    public function definition(): array
    {
        return [
            'variant' => MediaVariant::VARIANT_THUMB,
            'disk' => 'public',
            'path' => 'media/variants/'.fake()->unique()->uuid().'.jpg',
            'width' => 320,
            'height' => 180,
        ];
    }
}
