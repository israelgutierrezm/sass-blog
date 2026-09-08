<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Builder\Infrastructure\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Page>
 *
 * Page es scopeada por workspace (contexto) y requiere site_id: pasar el site con
 * ->for($site) o el atributo. En pruebas, fijar el contexto (actingForWorkspace).
 */
class PageFactory extends Factory
{
    protected $model = Page::class;

    public function definition(): array
    {
        $title = rtrim(fake()->unique()->sentence(3), '.');

        return [
            'title' => $title,
            'path' => '/'.Str::slug($title),
            'status' => Page::STATUS_DRAFT,
        ];
    }

    public function home(): static
    {
        return $this->state(fn () => ['title' => 'Inicio', 'path' => '/']);
    }
}
