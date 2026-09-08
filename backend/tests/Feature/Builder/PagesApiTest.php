<?php

declare(strict_types=1);

use App\Modules\Sites\Infrastructure\Models\Site;
use Database\Seeders\DatabaseSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

it('el owner crea una página con draft vacío', function () {
    ['user' => $u, 'ws' => $ws, 'site' => $site] = ownerWithSite();
    Sanctum::actingAs($u);

    $this->postJson(pagesUrl($ws, $site), ['title' => 'Inicio', 'path' => '/'])
        ->assertCreated()
        ->assertJsonPath('data.path', '/')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.draft_schema.sections', []);
});

it('lista y muestra páginas con su draft', function () {
    ['user' => $u, 'ws' => $ws, 'site' => $site] = ownerWithSite();
    $page = makePage($ws, $site);
    Sanctum::actingAs($u);

    $this->getJson(pagesUrl($ws, $site))->assertOk()->assertJsonCount(1, 'data');
    $this->getJson(pagesUrl($ws, $site)."/{$page->ulid}")
        ->assertOk()
        ->assertJsonPath('data.id', $page->ulid)
        ->assertJsonPath('data.draft_schema.schema_version', 1);
});

it('guarda el draft con un schema válido', function () {
    ['user' => $u, 'ws' => $ws, 'site' => $site] = ownerWithSite();
    $page = makePage($ws, $site);
    Sanctum::actingAs($u);

    $this->patchJson(pagesUrl($ws, $site)."/{$page->ulid}", ['schema' => heroSchema('Hola')])
        ->assertOk();

    $this->getJson(pagesUrl($ws, $site)."/{$page->ulid}")
        ->assertJsonPath('data.draft_schema.sections.0.props.heading', 'Hola');
});

it('rechaza un schema inválido con 422', function () {
    ['user' => $u, 'ws' => $ws, 'site' => $site] = ownerWithSite();
    $page = makePage($ws, $site);
    Sanctum::actingAs($u);

    $bad = ['schema_version' => 1, 'sections' => [[
        'id' => 'no-es-ulid', 'type' => 'hero', 'variant' => 'hero-centered',
        'visible' => true, 'props' => [], 'settings' => [],
    ]]];

    $this->patchJson(pagesUrl($ws, $site)."/{$page->ulid}", ['schema' => $bad])
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('schema');
});

it('publica una página con contenido (owner)', function () {
    ['user' => $u, 'ws' => $ws, 'site' => $site] = ownerWithSite();
    $page = makePage($ws, $site);
    Sanctum::actingAs($u);

    $this->patchJson(pagesUrl($ws, $site)."/{$page->ulid}", ['schema' => heroSchema('Publicado')])->assertOk();
    $this->postJson(pagesUrl($ws, $site)."/{$page->ulid}/publish")
        ->assertOk()
        ->assertJsonPath('data.status', 'published');
});

it('no publica una página con draft vacío (422)', function () {
    ['user' => $u, 'ws' => $ws, 'site' => $site] = ownerWithSite();
    $page = makePage($ws, $site);
    Sanctum::actingAs($u);

    $this->postJson(pagesUrl($ws, $site)."/{$page->ulid}/publish")->assertStatus(422);
});

it('un editor puede editar pero NO publicar (403)', function () {
    ['ws' => $ws, 'site' => $site] = ownerWithSite();
    $page = makePage($ws, $site);
    $editor = memberWithRole($ws, 'editor');
    Sanctum::actingAs($editor);

    $this->patchJson(pagesUrl($ws, $site)."/{$page->ulid}", ['schema' => heroSchema()])->assertOk();
    $this->postJson(pagesUrl($ws, $site)."/{$page->ulid}/publish")->assertForbidden();
});

it('un viewer NO puede crear (403)', function () {
    ['ws' => $ws, 'site' => $site] = ownerWithSite();
    $viewer = memberWithRole($ws, 'viewer');
    Sanctum::actingAs($viewer);

    $this->postJson(pagesUrl($ws, $site), ['title' => 'X', 'path' => '/x'])->assertForbidden();
});

it('un no-miembro no accede a las páginas del workspace (403)', function () {
    ['ws' => $wsA, 'site' => $siteA] = ownerWithSite('a@example.com');
    ['user' => $b] = registered('b@example.com');
    makePage($wsA, $siteA);

    Sanctum::actingAs($b);
    $this->getJson(pagesUrl($wsA, $siteA))->assertForbidden();
});

it('una página de otro site del mismo workspace no se resuelve (404)', function () {
    ['user' => $u, 'ws' => $ws, 'site' => $siteA] = ownerWithSite();
    $siteB = withinWorkspace($ws, fn () => Site::factory()->create());
    $page = makePage($ws, $siteA);

    Sanctum::actingAs($u);
    $this->getJson(pagesUrl($ws, $siteB)."/{$page->ulid}")->assertNotFound();
});

it('la respuesta preserva settings vacío como objeto {} (round-trip del admin)', function () {
    ['user' => $u, 'ws' => $ws, 'site' => $site] = ownerWithSite();
    $page = makePage($ws, $site);
    Sanctum::actingAs($u);

    $schema = ['schema_version' => 1, 'sections' => [[
        'id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV', 'type' => 'hero', 'variant' => 'hero-minimal',
        'visible' => true, 'props' => ['heading' => 'X'], 'settings' => (object) [],
    ]]];
    $this->patchJson(pagesUrl($ws, $site)."/{$page->ulid}", ['schema' => $schema])->assertOk();

    $content = $this->getJson(pagesUrl($ws, $site)."/{$page->ulid}")->assertOk()->getContent();
    expect($content)->toContain('"settings":{}');
});

it('preview-link devuelve una URL firmada', function () {
    ['user' => $u, 'ws' => $ws, 'site' => $site] = ownerWithSite();
    $page = makePage($ws, $site);
    Sanctum::actingAs($u);

    $url = $this->postJson(pagesUrl($ws, $site)."/{$page->ulid}/preview-link")
        ->assertOk()
        ->json('url');

    expect($url)->toContain('/api/v1/public/sites/')->toContain('signature=');
});
