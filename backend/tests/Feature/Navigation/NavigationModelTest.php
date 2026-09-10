<?php

declare(strict_types=1);

use App\Modules\Navigation\Infrastructure\Models\Menu;
use App\Modules\Navigation\Infrastructure\Models\MenuItem;
use App\Modules\Sites\Infrastructure\Models\Site;
use Illuminate\Database\QueryException;

it('crea un menú con workspace/site y ulid', function () {
    [$ws, $site] = builderSite();

    withinWorkspace($ws, function () use ($site) {
        $menu = Menu::factory()->create(['site_id' => $site->id, 'handle' => 'primary', 'name' => 'Principal']);

        expect($menu->workspace_id)->not->toBeNull()
            ->and($menu->site_id)->toBe($site->id)
            ->and($menu->ulid)->not->toBeNull()
            ->and($menu->handle)->toBe('primary');
    });
});

it('impide dos menús con el mismo handle en un sitio', function () {
    [$ws, $site] = builderSite();

    withinWorkspace($ws, function () use ($site) {
        Menu::factory()->create(['site_id' => $site->id, 'handle' => 'primary']);

        expect(fn () => Menu::factory()->create(['site_id' => $site->id, 'handle' => 'primary']))
            ->toThrow(QueryException::class);
    });
});

it('permite el mismo handle en sitios distintos', function () {
    [$ws, $siteA] = builderSite();
    $siteB = withinWorkspace($ws, fn () => Site::factory()->create());

    withinWorkspace($ws, function () use ($siteA, $siteB) {
        Menu::factory()->create(['site_id' => $siteA->id, 'handle' => 'primary']);
        Menu::factory()->create(['site_id' => $siteB->id, 'handle' => 'primary']);

        expect(Menu::forSite($siteA->id)->count())->toBe(1)
            ->and(Menu::forSite($siteB->id)->count())->toBe(1);
    });
});

it('aísla los menús entre workspaces (global scope)', function () {
    [$wsA, $siteA] = builderSite();
    [$wsB] = builderSite();

    withinWorkspace($wsA, fn () => Menu::factory()->create(['site_id' => $siteA->id]));

    withinWorkspace($wsB, fn () => expect(Menu::count())->toBe(0));
    withinWorkspace($wsA, fn () => expect(Menu::count())->toBe(1));
});

it('ordena los hijos por posición dentro del padre', function () {
    [$ws, $site] = builderSite();

    withinWorkspace($ws, function () use ($site) {
        $menu = Menu::factory()->create(['site_id' => $site->id]);
        $parent = MenuItem::factory()->create(['site_id' => $site->id, 'menu_id' => $menu->id, 'position' => 0]);
        MenuItem::factory()->create(['site_id' => $site->id, 'menu_id' => $menu->id, 'parent_id' => $parent->id, 'label' => 'B', 'position' => 1]);
        MenuItem::factory()->create(['site_id' => $site->id, 'menu_id' => $menu->id, 'parent_id' => $parent->id, 'label' => 'A', 'position' => 0]);

        expect($parent->children()->pluck('label')->all())->toBe(['A', 'B'])
            ->and($menu->items()->count())->toBe(3);
    });
});

it('borrar un menú arrastra sus ítems (cascade)', function () {
    [$ws, $site] = builderSite();

    withinWorkspace($ws, function () use ($site) {
        $menu = Menu::factory()->create(['site_id' => $site->id]);
        MenuItem::factory()->count(3)->create(['site_id' => $site->id, 'menu_id' => $menu->id]);

        $menu->delete();

        expect(MenuItem::count())->toBe(0);
    });
});

it('borrar un ítem padre arrastra sus hijos (cascade autorreferencia)', function () {
    [$ws, $site] = builderSite();

    withinWorkspace($ws, function () use ($site) {
        $menu = Menu::factory()->create(['site_id' => $site->id]);
        $parent = MenuItem::factory()->create(['site_id' => $site->id, 'menu_id' => $menu->id]);
        $child = MenuItem::factory()->create(['site_id' => $site->id, 'menu_id' => $menu->id, 'parent_id' => $parent->id]);

        $parent->delete();

        expect(MenuItem::whereKey($child->id)->exists())->toBeFalse();
    });
});
