<?php

declare(strict_types=1);

use App\Modules\Content\Application\Rendering\EntryBindings;
use App\Modules\Content\Infrastructure\Models\Entry;
use App\Modules\Sites\Application\CreateSite;
use Database\Seeders\DatabaseSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

it('renderiza el detalle de un artículo publicado con bindings resueltos', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site, 'articles' => $articles] = cmsOwnerContext();
    Sanctum::actingAs($user);

    [, $slug] = publishedArticle($ws->ulid, $site->ulid, $articles->ulid);

    $this->getJson("/api/v1/public/sites/{$site->ulid}/render?path=/blog/{$slug}")
        ->assertOk()
        ->assertJsonPath('data.page.kind', 'collection_template')
        ->assertJsonPath('data.entry.title', 'Mi Artículo')
        ->assertJsonPath('data.page.sections.0.props.heading', 'Mi Artículo')
        ->assertJsonPath('data.page.sections.0.props.subheading', 'Un resumen')
        ->assertJsonPath('data.page.sections.1.props.paragraphs.0', '<p>Cuerpo</p>')
        ->assertJsonPath('data.seo.title', 'Mi Artículo');
});

it('no renderiza un artículo en borrador (404)', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site, 'articles' => $articles] = cmsOwnerContext();
    Sanctum::actingAs($user);

    // Creado pero NO publicado.
    $base = "/api/v1/workspaces/{$ws->ulid}/sites/{$site->ulid}/collections/{$articles->ulid}/entries";
    $slug = $this->postJson($base, ['title' => 'Borrador', 'values' => ['body' => 'x']])->json('data.slug');

    $this->getJson("/api/v1/public/sites/{$site->ulid}/render?path=/blog/{$slug}")->assertNotFound();
});

it('devuelve 404 para un slug inexistente bajo el prefijo', function () {
    ['ws' => $ws, 'site' => $site] = cmsOwnerContext();

    $this->getJson("/api/v1/public/sites/{$site->ulid}/render?path=/blog/no-existe")->assertNotFound();
});

it('aísla el detalle entre sites (el artículo de A no se sirve en B)', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $siteA, 'articles' => $articlesA] = cmsOwnerContext();
    Sanctum::actingAs($user);

    [, $slug] = publishedArticle($ws->ulid, $siteA->ulid, $articlesA->ulid);

    $siteB = withinWorkspace($ws, fn () => app(CreateSite::class)->handle(['name' => 'Otro', 'slug' => 'otro']));

    $this->getJson("/api/v1/public/sites/{$siteB->ulid}/render?path=/blog/{$slug}")->assertNotFound();
});

it('el allow-set incluye campos bindeables y excluye media/json', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site, 'articles' => $articles] = cmsOwnerContext();
    Sanctum::actingAs($user);

    [$ulid] = publishedArticle($ws->ulid, $site->ulid, $articles->ulid);

    $map = withinWorkspace($ws, function () use ($ulid) {
        $entry = Entry::findByUlid($ulid)->load(['author', 'collection.fields']);

        return EntryBindings::map($entry);
    });

    expect($map)->toHaveKeys([
        'entry.title', 'entry.slug', 'entry.path',
        'entry.data.excerpt', 'entry.data.body', 'entry.data.reading_time', 'entry.data.featured',
    ])
        ->and($map)->not->toHaveKey('entry.data.featured_image') // media
        ->and($map)->not->toHaveKey('entry.data.tags');          // json
});
