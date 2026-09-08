<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Builder\Infrastructure\Models\PageVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PageVersion>
 *
 * Requiere page_id y site_id (pasar ->for($page) y el site). Normalmente las
 * versiones se crean por los servicios de dominio (CreatePage/PublishPage); este
 * factory es para pruebas de borde.
 */
class PageVersionFactory extends Factory
{
    protected $model = PageVersion::class;

    public function definition(): array
    {
        return [
            'version_number' => 1,
            'status' => PageVersion::STATUS_DRAFT,
            'schema_version' => 1,
            'schema' => ['schema_version' => 1, 'sections' => []],
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => PageVersion::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);
    }
}
