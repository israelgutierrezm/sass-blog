<?php

declare(strict_types=1);

use App\Modules\Navigation\Infrastructure\Models\Menu;
use App\Modules\Navigation\Infrastructure\Models\MenuItem;
use App\Modules\Shared\Domain\Rendering\SectionDataResolver;
use App\Modules\Tenancy\Infrastructure\Models\Workspace;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

/** Resuelve la sección `navigation` para el handle dado (dentro del contexto). */
function resolveNav(Workspace $ws, int $siteId, string $handle = 'primary'): array
{
    return withinWorkspace($ws, fn () => app(SectionDataResolver::class)->resolve(
        ['id' => 'NAV1', 'type' => 'navigation', 'props' => ['menu' => $handle]],
        ['workspace_id' => $ws->id, 'site_id' => $siteId],
    ));
}

it('el composite soporta navigation y collection-grid a la vez', function () {
    $resolver = app(SectionDataResolver::class);

    expect($resolver->supports('navigation'))->toBeTrue()
        ->and($resolver->supports('collection-grid'))->toBeTrue()
        ->and($resolver->supports('desconocido'))->toBeFalse();
});

it('resuelve el árbol con home/url y anida hijos ordenados', function () {
    [$ws, $site] = builderSite();

    withinWorkspace($ws, function () use ($site) {
        $menu = Menu::factory()->create(['site_id' => $site->id, 'handle' => 'primary']);
        $home = MenuItem::factory()->create(['site_id' => $site->id, 'menu_id' => $menu->id, 'label' => 'Inicio', 'link_type' => 'home', 'position' => 0]);
        MenuItem::factory()->create(['site_id' => $site->id, 'menu_id' => $menu->id, 'label' => 'Externo', 'link_type' => 'url', 'url' => 'https://ejemplo.com', 'position' => 1]);
        MenuItem::factory()->create(['site_id' => $site->id, 'menu_id' => $menu->id, 'parent_id' => $home->id, 'label' => 'Sub', 'link_type' => 'url', 'url' => '/sub', 'position' => 0]);
    });

    $res = resolveNav($ws, $site->id);

    expect($res['items'])->toHaveCount(2)
        ->and($res['items'][0])->toMatchArray(['label' => 'Inicio', 'url' => '/'])
        ->and($res['items'][0]['children'][0])->toMatchArray(['label' => 'Sub', 'url' => '/sub'])
        ->and($res['items'][1]['url'])->toBe('https://ejemplo.com');
});

it('resuelve un enlace de página a su path', function () {
    [$ws, $site] = builderSite();
    $page = makePage($ws, $site, 'Acerca', '/acerca');

    withinWorkspace($ws, function () use ($site, $page) {
        $menu = Menu::factory()->create(['site_id' => $site->id, 'handle' => 'primary']);
        MenuItem::factory()->create(['site_id' => $site->id, 'menu_id' => $menu->id, 'label' => 'Acerca', 'link_type' => 'page', 'target_ulid' => $page->ulid, 'url' => null]);
    });

    expect(resolveNav($ws, $site->id)['items'][0]['url'])->toBe('/acerca');
});

it('resuelve un enlace de entry EN VIVO: al cambiar el slug, cambia la url', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site, 'articles' => $articles] = cmsOwnerContext();
    Sanctum::actingAs($user);
    [$entryId, $slug] = publishedArticle($ws->ulid, $site->ulid, $articles->ulid);

    withinWorkspace($ws, function () use ($site, $entryId) {
        $menu = Menu::factory()->create(['site_id' => $site->id, 'handle' => 'primary']);
        MenuItem::factory()->create(['site_id' => $site->id, 'menu_id' => $menu->id, 'label' => 'Art', 'link_type' => 'entry', 'target_ulid' => $entryId, 'url' => null]);
    });

    expect(resolveNav($ws, $site->id)['items'][0]['url'])->toBe("/blog/{$slug}");

    // Cambiar el slug de la entry → la url del menú se recalcula (slug history).
    $this->patchJson("/api/v1/workspaces/{$ws->ulid}/sites/{$site->ulid}/collections/{$articles->ulid}/entries/{$entryId}", ['slug' => 'nuevo-slug'])->assertOk();

    expect(resolveNav($ws, $site->id)['items'][0]['url'])->toBe('/blog/nuevo-slug');
});

it('omite una referencia rota y su subárbol', function () {
    [$ws, $site] = builderSite();
    $page = makePage($ws, $site, 'Acerca', '/acerca');

    withinWorkspace($ws, function () use ($site, $page) {
        $menu = Menu::factory()->create(['site_id' => $site->id, 'handle' => 'primary']);
        // Destino inexistente → rota.
        $broken = MenuItem::factory()->create(['site_id' => $site->id, 'menu_id' => $menu->id, 'label' => 'Rota', 'link_type' => 'page', 'target_ulid' => '01ARZ3NDEKTSV4RRFFQ69G5FAV', 'url' => null, 'position' => 0]);
        // Hijo de la rota: debe desaparecer con el padre.
        MenuItem::factory()->create(['site_id' => $site->id, 'menu_id' => $menu->id, 'parent_id' => $broken->id, 'label' => 'HijoHuérfano', 'link_type' => 'home', 'position' => 0]);
        MenuItem::factory()->create(['site_id' => $site->id, 'menu_id' => $menu->id, 'label' => 'OK', 'link_type' => 'page', 'target_ulid' => $page->ulid, 'url' => null, 'position' => 1]);
    });

    $res = resolveNav($ws, $site->id);

    expect($res['items'])->toHaveCount(1)
        ->and($res['items'][0])->toMatchArray(['label' => 'OK', 'url' => '/acerca']);
});

it('no hace N+1: resuelve muchos ítems de página en consultas acotadas', function () {
    [$ws, $site] = builderSite();
    $pages = collect(range(1, 5))->map(fn (int $i) => makePage($ws, $site, "P{$i}", "/p{$i}"));

    withinWorkspace($ws, function () use ($site, $pages) {
        $menu = Menu::factory()->create(['site_id' => $site->id, 'handle' => 'primary']);
        $pages->each(fn ($page, int $i) => MenuItem::factory()->create([
            'site_id' => $site->id, 'menu_id' => $menu->id, 'link_type' => 'page', 'target_ulid' => $page->ulid, 'url' => null, 'position' => $i,
        ]));
    });

    DB::enableQueryLog();
    DB::flushQueryLog();
    $res = resolveNav($ws, $site->id);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    // menu + items + pages (batch) = 3; el conteo NO crece con el número de ítems.
    expect($res['items'])->toHaveCount(5)
        ->and($queries)->toBeLessThanOrEqual(4);
});
