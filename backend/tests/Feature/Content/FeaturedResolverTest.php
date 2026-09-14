<?php

declare(strict_types=1);

use App\Modules\Content\Infrastructure\Models\Collection;
use App\Modules\Content\Infrastructure\Models\Entry;
use App\Modules\Content\Infrastructure\Rendering\FeaturedResolver;

it('resuelve los artículos en el orden de items y sólo los publicados', function () {
    [$ws, $site] = builderSite();

    withinWorkspace($ws, function () use ($site) {
        $col = Collection::factory()->create(['site_id' => $site->id, 'handle' => 'articles', 'route_prefix' => 'blog']);
        $a = Entry::factory()->published()->create(['site_id' => $site->id, 'collection_id' => $col->id, 'title' => 'A']);
        $b = Entry::factory()->published()->create(['site_id' => $site->id, 'collection_id' => $col->id, 'title' => 'B']);
        $draft = Entry::factory()->create(['site_id' => $site->id, 'collection_id' => $col->id, 'title' => 'Borrador']);

        $result = app(FeaturedResolver::class)->resolve(
            ['type' => 'featured', 'props' => ['collection' => 'articles', 'items' => [$b->ulid, $a->ulid, $draft->ulid]]],
            ['site_id' => $site->id],
        );

        expect($result['total'])->toBe(2)                       // el borrador se excluye
            ->and($result['items'][0]['title'])->toBe('B')       // respeta el orden de items
            ->and($result['items'][1]['title'])->toBe('A');
    });
});

it('devuelve vacío sin items', function () {
    [$ws, $site] = builderSite();

    withinWorkspace($ws, function () use ($site) {
        Collection::factory()->create(['site_id' => $site->id, 'handle' => 'articles']);

        $result = app(FeaturedResolver::class)->resolve(
            ['type' => 'featured', 'props' => ['collection' => 'articles', 'items' => []]],
            ['site_id' => $site->id],
        );

        expect($result['items'])->toBe([])->and($result['total'])->toBe(0);
    });
});

it('ignora ULIDs inexistentes o de otra colección', function () {
    [$ws, $site] = builderSite();

    withinWorkspace($ws, function () use ($site) {
        $col = Collection::factory()->create(['site_id' => $site->id, 'handle' => 'articles', 'route_prefix' => 'blog']);
        $a = Entry::factory()->published()->create(['site_id' => $site->id, 'collection_id' => $col->id, 'title' => 'A']);

        $result = app(FeaturedResolver::class)->resolve(
            ['type' => 'featured', 'props' => ['collection' => 'articles', 'items' => [$a->ulid, '01ARZ3NDEKTSV4RRFFQ69G5FAV']]],
            ['site_id' => $site->id],
        );

        expect($result['total'])->toBe(1)->and($result['items'][0]['title'])->toBe('A');
    });
});
