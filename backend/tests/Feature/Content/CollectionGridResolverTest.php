<?php

declare(strict_types=1);

use App\Modules\Builder\Application\CreatePage;
use App\Modules\Builder\Application\PublishPage;
use App\Modules\Builder\Application\SaveDraft;
use App\Modules\Content\Infrastructure\Models\Author;
use App\Modules\Content\Infrastructure\Models\Category;
use App\Modules\Content\Infrastructure\Models\Entry;
use App\Modules\Content\Infrastructure\Rendering\CollectionGridResolver;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

it('proyecta tarjetas con path, excerpt, imagen, autor y categoría', function () {
    ['ws' => $ws, 'site' => $site, 'articles' => $articles] = cmsOwnerContext();

    $out = withinWorkspace($ws, function () use ($site, $articles) {
        $author = Author::factory()->create(['site_id' => $site->id, 'name' => 'Ada', 'slug' => 'ada']);
        $general = Category::query()->where('collection_id', $articles->id)->where('slug', 'general')->firstOrFail();

        $entry = Entry::factory()->published()->create([
            'site_id' => $site->id,
            'collection_id' => $articles->id,
            'author_id' => $author->id,
            'title' => 'Hola',
            'slug' => 'hola',
            'data' => ['excerpt' => 'Un resumen', 'featured_image' => 'https://cdn.example.com/y.png'],
        ]);
        $entry->syncCategories([$general->id]);

        return app(CollectionGridResolver::class)->resolve(
            ['props' => ['collection' => 'articles']],
            ['site_id' => $site->id],
        );
    });

    $card = $out['items'][0];
    expect($out['total'])->toBe(1)
        ->and($card['title'])->toBe('Hola')
        ->and($card['path'])->toBe('/blog/hola')
        ->and($card['excerpt'])->toBe('Un resumen')
        ->and($card['image'])->toBe('https://cdn.example.com/y.png')
        ->and($card['author']['slug'])->toBe('ada')
        ->and($card['category']['slug'])->toBe('general')
        ->and($card['date'])->not->toBeNull();
});

it('sólo incluye entries publicadas', function () {
    ['ws' => $ws, 'site' => $site, 'articles' => $articles] = cmsOwnerContext();

    $out = withinWorkspace($ws, function () use ($site, $articles) {
        Entry::factory()->published()->create(['site_id' => $site->id, 'collection_id' => $articles->id, 'title' => 'Pub', 'slug' => 'pub']);
        Entry::factory()->create(['site_id' => $site->id, 'collection_id' => $articles->id, 'title' => 'Draft', 'slug' => 'draft']);

        return app(CollectionGridResolver::class)->resolve(['props' => ['collection' => 'articles']], ['site_id' => $site->id]);
    });

    expect($out['total'])->toBe(1)
        ->and($out['items'])->toHaveCount(1)
        ->and($out['items'][0]['title'])->toBe('Pub');
});

it('filtra por categoría y respeta order y limit', function () {
    ['ws' => $ws, 'site' => $site, 'articles' => $articles] = cmsOwnerContext();

    [$filtered, $ordered, $limited] = withinWorkspace($ws, function () use ($site, $articles) {
        $news = Category::create(['site_id' => $site->id, 'collection_id' => $articles->id, 'name' => 'Noticias', 'slug' => 'noticias', 'position' => 1]);

        $alpha = Entry::factory()->published()->create(['site_id' => $site->id, 'collection_id' => $articles->id, 'title' => 'Alpha', 'slug' => 'alpha']);
        Entry::factory()->published()->create(['site_id' => $site->id, 'collection_id' => $articles->id, 'title' => 'Beta', 'slug' => 'beta']);
        Entry::factory()->published()->create(['site_id' => $site->id, 'collection_id' => $articles->id, 'title' => 'Gamma', 'slug' => 'gamma']);
        $alpha->syncCategories([$news->id]);

        $resolver = app(CollectionGridResolver::class);

        return [
            $resolver->resolve(['props' => ['collection' => 'articles', 'category' => 'noticias']], ['site_id' => $site->id]),
            $resolver->resolve(['props' => ['collection' => 'articles', 'order' => 'title']], ['site_id' => $site->id]),
            $resolver->resolve(['props' => ['collection' => 'articles', 'order' => 'title', 'limit' => 2]], ['site_id' => $site->id]),
        ];
    });

    expect($filtered['total'])->toBe(1)
        ->and($filtered['items'][0]['title'])->toBe('Alpha');

    expect(array_column($ordered['items'], 'title'))->toBe(['Alpha', 'Beta', 'Gamma']);

    expect($limited['items'])->toHaveCount(2)
        ->and($limited['total'])->toBe(3);
});

it('resuelve sin N+1 (consultas acotadas con N entries)', function () {
    ['ws' => $ws, 'site' => $site, 'articles' => $articles] = cmsOwnerContext();

    [$result, $queryCount] = withinWorkspace($ws, function () use ($site, $articles) {
        $general = Category::query()->where('collection_id', $articles->id)->where('slug', 'general')->firstOrFail();

        for ($i = 0; $i < 5; $i++) {
            $author = Author::factory()->create(['site_id' => $site->id]);
            $entry = Entry::factory()->published()->create(['site_id' => $site->id, 'collection_id' => $articles->id, 'author_id' => $author->id]);
            $entry->syncCategories([$general->id]);
        }

        DB::connection()->flushQueryLog();
        DB::connection()->enableQueryLog();
        $result = app(CollectionGridResolver::class)->resolve(['props' => ['collection' => 'articles', 'limit' => 10]], ['site_id' => $site->id]);
        $count = count(DB::connection()->getQueryLog());
        DB::connection()->disableQueryLog();

        return [$result, $count];
    });

    expect($result['total'])->toBe(5)
        ->and($result['items'])->toHaveCount(5)
        ->and($queryCount)->toBeLessThanOrEqual(6);
});

it('el render estático incluye el sidecar resolved con las tarjetas del grid', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site, 'articles' => $articles] = cmsOwnerContext();
    Sanctum::actingAs($user);

    $base = "/api/v1/workspaces/{$ws->ulid}/sites/{$site->ulid}/collections/{$articles->ulid}/entries";
    foreach (['Uno', 'Dos'] as $title) {
        $id = $this->postJson($base, ['title' => $title, 'values' => ['body' => 'x', 'excerpt' => "R{$title}"]])->json('data.id');
        $this->postJson("{$base}/{$id}/publish")->assertOk();
    }

    $gridId = Str::upper((string) Str::ulid());
    $schema = [
        'schema_version' => 1,
        'sections' => [[
            'id' => $gridId,
            'type' => 'collection-grid',
            'variant' => 'collection-grid-cards',
            'visible' => true,
            'props' => ['collection' => 'articles', 'limit' => 6, 'order' => 'recent', 'columns' => 3],
            'settings' => ['spacing' => ['top' => 'lg', 'bottom' => 'lg']],
        ]],
    ];

    withinWorkspace($ws, function () use ($site, $schema, $user) {
        $page = app(CreatePage::class)->handle($site, 'Home', '/', $user->id);
        app(SaveDraft::class)->handle($page, $schema);
        app(PublishPage::class)->handle($page, $user->id);
    });

    $this->getJson("/api/v1/public/sites/{$site->ulid}/render?path=/")
        ->assertOk()
        ->assertJsonPath("data.resolved.{$gridId}.total", 2)
        ->assertJsonCount(2, "data.resolved.{$gridId}.items")
        ->assertJsonPath("data.resolved.{$gridId}.items.0.title", 'Dos');
});
