<?php

declare(strict_types=1);

use App\Modules\Builder\Application\PublishPage;
use App\Modules\Builder\Application\SaveDraft;
use App\Modules\Navigation\Infrastructure\Models\Menu;
use App\Modules\Navigation\Infrastructure\Models\MenuItem;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

/** Schema con un hero (estático) + una sección navigation que referencia un menú. */
function pageWithNav(string $navId): array
{
    return [
        'schema_version' => 1,
        'sections' => [
            [
                'id' => Str::upper((string) Str::ulid()),
                'type' => 'hero', 'variant' => 'hero-centered', 'visible' => true,
                'props' => ['heading' => 'Bienvenido'],
                'settings' => ['spacing' => ['top' => 'md', 'bottom' => 'md']],
            ],
            [
                'id' => $navId,
                'type' => 'navigation', 'variant' => 'navigation-horizontal', 'visible' => true,
                'props' => ['menu' => 'primary'],
                'settings' => ['spacing' => ['top' => 'sm', 'bottom' => 'sm']],
            ],
        ],
    ];
}

it('publica una página con sección navigation y el render embebe el árbol del menú', function () {
    ['ws' => $ws, 'site' => $site] = ownerWithSite();
    $navId = '01BX5ZZKBKACTAV9WEVGEMMVRZ';

    // Menú `primary` con dos ítems (home + url).
    withinWorkspace($ws, function () use ($site) {
        $menu = Menu::factory()->create(['site_id' => $site->id, 'handle' => 'primary']);
        MenuItem::factory()->create(['site_id' => $site->id, 'menu_id' => $menu->id, 'label' => 'Inicio', 'link_type' => 'home', 'position' => 0]);
        MenuItem::factory()->create(['site_id' => $site->id, 'menu_id' => $menu->id, 'label' => 'Contacto', 'link_type' => 'url', 'url' => '/contacto', 'position' => 1]);
    });

    // Página publicada con la sección navigation.
    $page = makePage($ws, $site, 'Home', '/');
    withinWorkspace($ws, function () use ($page, $navId) {
        app(SaveDraft::class)->handle($page, pageWithNav($navId));
        app(PublishPage::class)->handle($page->fresh());
    });

    $this->getJson("/api/v1/public/sites/{$site->ulid}/render?path=/")
        ->assertOk()
        ->assertJsonPath("data.resolved.{$navId}.items.0.label", 'Inicio')
        ->assertJsonPath("data.resolved.{$navId}.items.0.url", '/')
        ->assertJsonPath("data.resolved.{$navId}.items.1.label", 'Contacto')
        ->assertJsonPath("data.resolved.{$navId}.items.1.url", '/contacto');
});
