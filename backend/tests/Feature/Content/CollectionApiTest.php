<?php

declare(strict_types=1);

use App\Modules\Sites\Application\CreateSite;
use Database\Seeders\DatabaseSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

function collectionsUrl(string $wsUlid, string $siteUlid): string
{
    return "/api/v1/workspaces/{$wsUlid}/sites/{$siteUlid}/collections";
}

it('lista las colecciones del sitio', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = cmsOwnerContext();
    Sanctum::actingAs($user);

    // Sólo 'articles' (sembrada).
    $this->getJson(collectionsUrl($ws->ulid, $site->ulid))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.handle', 'articles');
});

it('crea una colección con campos', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = cmsOwnerContext();
    Sanctum::actingAs($user);

    $this->postJson(collectionsUrl($ws->ulid, $site->ulid), [
        'name' => 'Proyectos',
        'handle' => 'projects',
        'kind' => 'generic',
        'fields' => [
            ['key' => 'summary', 'label' => 'Resumen', 'type' => 'textarea'],
            ['key' => 'homepage', 'type' => 'url', 'required' => true],
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('data.handle', 'projects')
        ->assertJsonCount(2, 'data.fields')
        ->assertJsonPath('data.fields.0.key', 'summary')
        ->assertJsonPath('data.fields.1.type', 'url');
});

it('rechaza una key de campo con punto (cierra el bypass de notación de puntos)', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = cmsOwnerContext();
    Sanctum::actingAs($user);

    $this->postJson(collectionsUrl($ws->ulid, $site->ulid), [
        'name' => 'X',
        'fields' => [['key' => 'seo.canonical', 'type' => 'url']],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('fields.0.key');
});

it('rechaza un tipo de campo fuera del catálogo cerrado', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = cmsOwnerContext();
    Sanctum::actingAs($user);

    $this->postJson(collectionsUrl($ws->ulid, $site->ulid), [
        'name' => 'X',
        'fields' => [['key' => 'foo', 'type' => 'wysiwyg']],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('fields.0.type');
});

it('desambigua el handle en colisión', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = cmsOwnerContext();
    Sanctum::actingAs($user);

    // 'articles' ya existe (sembrada).
    $this->postJson(collectionsUrl($ws->ulid, $site->ulid), ['name' => 'Otra', 'handle' => 'articles'])
        ->assertCreated()
        ->assertJsonPath('data.handle', 'articles-2');
});

it('rechaza un route_prefix duplicado', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = cmsOwnerContext();
    Sanctum::actingAs($user);

    // 'blog' ya lo usa la colección de artículos.
    $this->postJson(collectionsUrl($ws->ulid, $site->ulid), ['name' => 'Otra', 'route_prefix' => 'blog'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('route_prefix');
});

it('actualiza metadatos de la colección', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site, 'articles' => $articles] = cmsOwnerContext();
    Sanctum::actingAs($user);

    $this->patchJson(collectionsUrl($ws->ulid, $site->ulid)."/{$articles->ulid}", ['name' => 'Bitácora'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Bitácora');
});

it('el plan free no puede acceder (capability 403)', function () {
    ['user' => $user, 'workspace' => $ws] = registered('free@example.com');
    $site = withinWorkspace($ws, fn () => app(CreateSite::class)->handle(['name' => 'Blog', 'slug' => 'blog']));
    Sanctum::actingAs($user);

    $this->getJson(collectionsUrl($ws->ulid, $site->ulid))->assertForbidden();
});

it('editor no crea colecciones pero las ve; viewer las ve (RBAC)', function () {
    ['ws' => $ws, 'site' => $site] = cmsOwnerContext();
    $url = collectionsUrl($ws->ulid, $site->ulid);

    Sanctum::actingAs(memberWithRole($ws, 'editor'));
    $this->getJson($url)->assertOk();
    $this->postJson($url, ['name' => 'No permitido'])->assertForbidden();

    Sanctum::actingAs(memberWithRole($ws, 'viewer'));
    $this->getJson($url)->assertOk();
});

it('aísla colecciones entre sites del mismo workspace', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $siteA, 'articles' => $articlesA] = cmsOwnerContext();
    Sanctum::actingAs($user);

    $siteB = withinWorkspace($ws, fn () => app(CreateSite::class)->handle(['name' => 'Otro', 'slug' => 'otro']));

    // La colección de A no se resuelve bajo B (404).
    $this->getJson(collectionsUrl($ws->ulid, $siteB->ulid)."/{$articlesA->ulid}")->assertNotFound();
});
