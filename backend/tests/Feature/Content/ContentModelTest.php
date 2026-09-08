<?php

declare(strict_types=1);

use App\Modules\Builder\Infrastructure\Models\Page;
use App\Modules\Content\Domain\CollectionKind;
use App\Modules\Content\Domain\Fields\FieldType;
use App\Modules\Content\Infrastructure\Models\Category;
use App\Modules\Content\Infrastructure\Models\Collection;
use App\Modules\Content\Infrastructure\Models\CollectionField;
use App\Modules\Content\Infrastructure\Models\Entry;
use App\Modules\Sites\Infrastructure\Models\Site;
use Illuminate\Database\QueryException;

it('crea una colección article con campos, scopeada por workspace y site', function () {
    [$ws, $site] = builderSite();
    actingForWorkspace($ws);

    $collection = Collection::factory()->article()->create(['site_id' => $site->id]);

    expect($collection->workspace_id)->toBe($ws->id)
        ->and($collection->site_id)->toBe($site->id)
        ->and($collection->kind)->toBe(CollectionKind::Article)
        ->and($collection->route_prefix)->toBe('blog')
        ->and($collection->ulid)->toHaveLength(26);

    CollectionField::factory()->create([
        'site_id' => $site->id,
        'collection_id' => $collection->id,
        'key' => 'excerpt',
        'type' => FieldType::Textarea->value,
    ]);

    expect($collection->fields()->count())->toBe(1)
        ->and($collection->fields()->first()->type)->toBe(FieldType::Textarea);
});

it('crea un entry con data JSON y lo aísla por site (ScopedToSite)', function () {
    [$ws, $site] = builderSite();
    $siteB = withinWorkspace($ws, fn () => Site::factory()->create());
    actingForWorkspace($ws);

    $colA = Collection::factory()->create(['site_id' => $site->id]);
    $colB = Collection::factory()->create(['site_id' => $siteB->id]);

    Entry::factory()->create(['site_id' => $site->id, 'collection_id' => $colA->id, 'data' => ['x' => 1]]);
    Entry::factory()->create(['site_id' => $siteB->id, 'collection_id' => $colB->id]);

    // Ambos en el MISMO workspace; ScopedToSite los separa.
    expect(Entry::count())->toBe(2)
        ->and(Entry::forSite($site->id)->count())->toBe(1)
        ->and(Entry::forSite($siteB->id)->count())->toBe(1)
        ->and(Entry::forSite($site->id)->first()->data)->toEqual(['x' => 1]);
});

it('sincroniza categorías rellenando el tenant del pivote', function () {
    [$ws, $site] = builderSite();
    actingForWorkspace($ws);
    $col = Collection::factory()->create(['site_id' => $site->id]);
    $entry = Entry::factory()->create(['site_id' => $site->id, 'collection_id' => $col->id]);
    $cat = Category::factory()->create(['site_id' => $site->id, 'collection_id' => $col->id]);

    $entry->syncCategories([$cat->id]);

    $linked = $entry->categories()->first();
    expect($entry->categories()->count())->toBe(1)
        ->and($linked->id)->toBe($cat->id)
        ->and($linked->pivot->workspace_id)->toBe($ws->id)
        ->and($linked->pivot->site_id)->toBe($site->id);
});

it('el scope published excluye drafts y fechas futuras', function () {
    [$ws, $site] = builderSite();
    actingForWorkspace($ws);
    $col = Collection::factory()->create(['site_id' => $site->id]);

    Entry::factory()->create(['site_id' => $site->id, 'collection_id' => $col->id]); // draft
    Entry::factory()->published()->create(['site_id' => $site->id, 'collection_id' => $col->id]); // vivo
    Entry::factory()->create([
        'site_id' => $site->id, 'collection_id' => $col->id,
        'status' => Entry::STATUS_PUBLISHED, 'published_at' => now()->addDay(),
    ]); // futuro

    expect(Entry::published()->count())->toBe(1);
});

it('pages: el CHECK exige path según kind', function () {
    [$ws, $site] = builderSite();
    actingForWorkspace($ws);

    $badTemplate = new Page;
    $badTemplate->site_id = $site->id;
    $badTemplate->title = 'X';
    $badTemplate->kind = Page::KIND_COLLECTION_TEMPLATE;
    $badTemplate->path = '/no-debería';
    expect(fn () => $badTemplate->save())->toThrow(QueryException::class);

    $badStandard = new Page;
    $badStandard->site_id = $site->id;
    $badStandard->title = 'Y';
    $badStandard->kind = Page::KIND_STANDARD;
    $badStandard->path = null;
    expect(fn () => $badStandard->save())->toThrow(QueryException::class);

    $template = new Page;
    $template->site_id = $site->id;
    $template->title = 'Plantilla';
    $template->kind = Page::KIND_COLLECTION_TEMPLATE;
    $template->path = null;
    $template->save();
    expect($template->exists)->toBeTrue();
});
