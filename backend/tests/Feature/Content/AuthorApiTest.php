<?php

declare(strict_types=1);

use App\Modules\Sites\Application\CreateSite;
use Database\Seeders\DatabaseSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

function authorsUrl(string $wsUlid, string $siteUlid): string
{
    return "/api/v1/workspaces/{$wsUlid}/sites/{$siteUlid}/authors";
}

it('el owner crea y lista autores', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = cmsOwnerContext();
    Sanctum::actingAs($user);
    $url = authorsUrl($ws->ulid, $site->ulid);

    $this->postJson($url, ['name' => 'Ada Lovelace'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Ada Lovelace')
        ->assertJsonPath('data.slug', 'ada-lovelace');

    // 'redaccion' por defecto (sembrado) + la nueva.
    $this->getJson($url)->assertOk()->assertJsonCount(2, 'data');
});

it('exige el nombre', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = cmsOwnerContext();
    Sanctum::actingAs($user);

    $this->postJson(authorsUrl($ws->ulid, $site->ulid), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

it('rechaza un slug duplicado en el sitio', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = cmsOwnerContext();
    Sanctum::actingAs($user);

    // 'redaccion' ya existe (autor por defecto).
    $this->postJson(authorsUrl($ws->ulid, $site->ulid), ['name' => 'Otro', 'slug' => 'redaccion'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('slug');
});

it('actualiza y elimina un autor', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = cmsOwnerContext();
    Sanctum::actingAs($user);
    $url = authorsUrl($ws->ulid, $site->ulid);

    $ulid = $this->postJson($url, ['name' => 'Grace Hopper'])->json('data.id');

    $this->patchJson("{$url}/{$ulid}", ['bio' => 'Pionera del cómputo'])
        ->assertOk()
        ->assertJsonPath('data.bio', 'Pionera del cómputo');

    $this->deleteJson("{$url}/{$ulid}")->assertNoContent();
    $this->getJson("{$url}/{$ulid}")->assertNotFound();
});

it('el plan free no puede acceder (capability 403)', function () {
    ['user' => $user, 'workspace' => $ws] = registered('free@example.com');
    $site = withinWorkspace($ws, fn () => app(CreateSite::class)->handle(['name' => 'Blog', 'slug' => 'blog']));
    Sanctum::actingAs($user);

    $this->getJson(authorsUrl($ws->ulid, $site->ulid))->assertForbidden();
});

it('editor gestiona autores; viewer no (RBAC)', function () {
    ['ws' => $ws, 'site' => $site] = cmsOwnerContext();
    $url = authorsUrl($ws->ulid, $site->ulid);

    Sanctum::actingAs(memberWithRole($ws, 'editor'));
    $this->getJson($url)->assertOk();
    $this->postJson($url, ['name' => 'Editor Autor'])->assertCreated();

    Sanctum::actingAs(memberWithRole($ws, 'viewer'));
    $this->getJson($url)->assertForbidden();
});

it('aísla autores entre sites del mismo workspace', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $siteA] = cmsOwnerContext();
    Sanctum::actingAs($user);

    $ulid = $this->postJson(authorsUrl($ws->ulid, $siteA->ulid), ['name' => 'Solo A'])->json('data.id');

    $siteB = withinWorkspace($ws, fn () => app(CreateSite::class)->handle(['name' => 'Otro', 'slug' => 'otro']));

    // El autor de A no se resuelve bajo B (404, no fuga cross-site).
    $this->getJson(authorsUrl($ws->ulid, $siteB->ulid)."/{$ulid}")->assertNotFound();
});
