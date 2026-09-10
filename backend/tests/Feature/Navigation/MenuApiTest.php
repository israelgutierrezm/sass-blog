<?php

declare(strict_types=1);

use App\Modules\Sites\Infrastructure\Models\Site;
use Database\Seeders\DatabaseSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

it('el owner crea un menú y lo lista', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = ownerWithSite();
    Sanctum::actingAs($user);
    $url = menusUrl($ws->ulid, $site->ulid);

    $this->postJson($url, ['handle' => 'primary', 'name' => 'Principal'])
        ->assertCreated()
        ->assertJsonPath('data.handle', 'primary')
        ->assertJsonPath('data.name', 'Principal');

    $this->getJson($url)->assertOk()->assertJsonCount(1, 'data');
});

it('rechaza un handle duplicado en el mismo sitio', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = ownerWithSite();
    Sanctum::actingAs($user);
    $url = menusUrl($ws->ulid, $site->ulid);

    $this->postJson($url, ['handle' => 'primary', 'name' => 'A'])->assertCreated();
    $this->postJson($url, ['handle' => 'primary', 'name' => 'B'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('handle');
});

it('rechaza un handle con formato inválido', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = ownerWithSite();
    Sanctum::actingAs($user);

    $this->postJson(menusUrl($ws->ulid, $site->ulid), ['handle' => 'Primary Nav', 'name' => 'X'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('handle');
});

it('actualiza y elimina un menú', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = ownerWithSite();
    Sanctum::actingAs($user);
    $url = menusUrl($ws->ulid, $site->ulid);

    $id = $this->postJson($url, ['handle' => 'footer', 'name' => 'Pie'])->json('data.id');

    $this->patchJson("{$url}/{$id}", ['name' => 'Pie de página'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Pie de página');

    $this->deleteJson("{$url}/{$id}")->assertNoContent();
    $this->getJson($url)->assertJsonCount(0, 'data');
});

it('editor gestiona; viewer no puede (RBAC menu.manage)', function () {
    ['ws' => $ws, 'site' => $site] = ownerWithSite();
    $url = menusUrl($ws->ulid, $site->ulid);

    Sanctum::actingAs(memberWithRole($ws, 'editor'));
    $this->postJson($url, ['handle' => 'primary', 'name' => 'P'])->assertCreated();

    Sanctum::actingAs(memberWithRole($ws, 'viewer'));
    $this->getJson($url)->assertForbidden();
    $this->postJson($url, ['handle' => 'footer', 'name' => 'F'])->assertForbidden();
});

it('exige autenticación', function () {
    ['ws' => $ws, 'site' => $site] = ownerWithSite();

    $this->getJson(menusUrl($ws->ulid, $site->ulid))->assertUnauthorized();
});

it('aísla los menús entre sitios del mismo workspace', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $siteA] = ownerWithSite();
    Sanctum::actingAs($user);

    $id = $this->postJson(menusUrl($ws->ulid, $siteA->ulid), ['handle' => 'primary', 'name' => 'A'])->json('data.id');

    $siteB = withinWorkspace($ws, fn () => Site::factory()->create());

    $this->getJson(menusUrl($ws->ulid, $siteB->ulid)."/{$id}")->assertNotFound();
});
