<?php

declare(strict_types=1);

use App\Modules\Content\Application\CreateCollection;
use App\Modules\Content\Domain\CollectionKind;
use App\Modules\Content\Infrastructure\Models\Author;
use App\Modules\Content\Infrastructure\Models\Category;
use App\Modules\Content\Infrastructure\Models\Collection;
use App\Modules\Content\Listeners\ProvisionArticleContent;
use App\Modules\Sites\Application\CreateSite;
use App\Modules\Sites\Events\SiteCreated;
use App\Modules\Sites\Infrastructure\Models\Site;
use Database\Seeders\DatabaseSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

it('al crear un site siembra la colección de artículos con sus campos', function () {
    ['workspace' => $ws] = registered();

    $site = withinWorkspace($ws, fn () => app(CreateSite::class)->handle(['name' => 'Blog', 'slug' => 'blog']));

    withinWorkspace($ws, function () use ($site) {
        $collection = Collection::forSite($site->id)->where('handle', 'articles')->with('fields')->first();

        expect($collection)->not->toBeNull()
            ->and($collection->kind)->toBe(CollectionKind::Article)
            ->and($collection->route_prefix)->toBe('blog')
            ->and($collection->fields)->toHaveCount(6)
            ->and($collection->fields->pluck('key')->all())
            ->toBe(['excerpt', 'body', 'featured_image', 'tags', 'reading_time', 'featured'])
            ->and($collection->fields->firstWhere('key', 'body')->required)->toBeTrue();

        // Autor y categoría por defecto, scopeados al site.
        expect(Author::forSite($site->id)->where('slug', 'redaccion')->exists())->toBeTrue();

        $general = Category::forSite($site->id)->where('slug', 'general')->first();
        expect($general)->not->toBeNull()
            ->and($general->collection_id)->toBe($collection->id);
    });
});

it('el sembrado es idempotente: no duplica al re-emitir SiteCreated', function () {
    ['workspace' => $ws] = registered();

    $site = withinWorkspace($ws, fn () => app(CreateSite::class)->handle(['name' => 'Blog', 'slug' => 'blog']));

    withinWorkspace($ws, function () use ($ws, $site) {
        // Re-emitir el evento no debe crear una segunda colección ni lanzar.
        app(ProvisionArticleContent::class)->handle(new SiteCreated($ws->id, $site->id));

        expect(Collection::forSite($site->id)->where('handle', 'articles')->count())->toBe(1)
            ->and(Author::forSite($site->id)->where('slug', 'redaccion')->count())->toBe(1);
    });
});

it('cada site del workspace recibe su propia colección de artículos (aislamiento)', function () {
    ['workspace' => $ws] = registered();

    [$a, $b] = withinWorkspace($ws, fn () => [
        app(CreateSite::class)->handle(['name' => 'Uno', 'slug' => 'uno']),
        app(CreateSite::class)->handle(['name' => 'Dos', 'slug' => 'dos']),
    ]);

    withinWorkspace($ws, function () use ($a, $b) {
        $ca = Collection::forSite($a->id)->where('handle', 'articles')->first();
        $cb = Collection::forSite($b->id)->where('handle', 'articles')->first();

        expect($ca)->not->toBeNull()
            ->and($cb)->not->toBeNull()
            ->and($ca->id)->not->toBe($cb->id)
            ->and($ca->site_id)->toBe($a->id)
            ->and($cb->site_id)->toBe($b->id);
    });
});

it('CreateCollection hace único el handle ante colisión', function () {
    ['workspace' => $ws] = registered();

    $site = withinWorkspace($ws, fn () => app(CreateSite::class)->handle(['name' => 'Blog', 'slug' => 'blog']));

    withinWorkspace($ws, function () use ($site) {
        // Ya existe 'articles' (sembrada); una nueva con el mismo handle se desambigua.
        $other = app(CreateCollection::class)->handle($site, ['handle' => 'articles', 'name' => 'Otra']);

        expect($other->handle)->toBe('articles-2');
    });
});

it('el endpoint POST /sites dispara el sembrado (end-to-end)', function () {
    ['user' => $user, 'workspace' => $ws] = registered();
    Sanctum::actingAs($user);

    $ulid = $this->postJson("/api/v1/workspaces/{$ws->ulid}/sites", [
        'name' => 'Revista',
        'slug' => 'revista',
    ])->assertCreated()->json('data.id');

    withinWorkspace($ws, function () use ($ulid) {
        $site = Site::findByUlid($ulid);

        expect($site)->not->toBeNull()
            ->and(Collection::forSite($site->id)->where('handle', 'articles')->exists())->toBeTrue();
    });
});
