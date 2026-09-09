<?php

declare(strict_types=1);

use App\Modules\Sites\Infrastructure\Models\Site;
use Database\Seeders\DatabaseSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

function redirectsUrl(string $wsUlid, string $siteUlid): string
{
    return "/api/v1/workspaces/{$wsUlid}/sites/{$siteUlid}/redirects";
}

it('el owner crea un redirect y lo lista', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = ownerWithSite();
    Sanctum::actingAs($user);
    $url = redirectsUrl($ws->ulid, $site->ulid);

    $this->postJson($url, ['from_path' => '/viejo', 'to_path' => '/nuevo'])
        ->assertCreated()
        ->assertJsonPath('data.from_path', '/viejo')
        ->assertJsonPath('data.to_path', '/nuevo')
        ->assertJsonPath('data.status', 301)
        ->assertJsonPath('data.source', 'manual')
        ->assertJsonPath('data.is_active', true);

    $this->getJson($url)->assertOk()->assertJsonCount(1, 'data');
});

it('normaliza las rutas: barra final y barra inicial ausente', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = ownerWithSite();
    Sanctum::actingAs($user);

    $this->postJson(redirectsUrl($ws->ulid, $site->ulid), ['from_path' => '/viejo/', 'to_path' => 'nuevo'])
        ->assertCreated()
        ->assertJsonPath('data.from_path', '/viejo')
        ->assertJsonPath('data.to_path', '/nuevo');
});

it('colapsa un destino protocol-relative a ruta interna (anti open-redirect)', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = ownerWithSite();
    Sanctum::actingAs($user);

    // '//evil.com' se normaliza a '/evil.com' (interno del propio sitio), nunca sale fuera.
    $this->postJson(redirectsUrl($ws->ulid, $site->ulid), ['from_path' => '/a', 'to_path' => '//evil.com'])
        ->assertCreated()
        ->assertJsonPath('data.to_path', '/evil.com');
});

it('rechaza un destino con barra invertida (open-redirect por backslash)', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = ownerWithSite();
    Sanctum::actingAs($user);

    $this->postJson(redirectsUrl($ws->ulid, $site->ulid), ['from_path' => '/a', 'to_path' => '/\evil.com'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('to_path');
});

it('rechaza el bucle from == to', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = ownerWithSite();
    Sanctum::actingAs($user);

    $this->postJson(redirectsUrl($ws->ulid, $site->ulid), ['from_path' => '/loop', 'to_path' => '/loop'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('to_path');
});

it('rechaza un from_path duplicado en el mismo sitio', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = ownerWithSite();
    Sanctum::actingAs($user);
    $url = redirectsUrl($ws->ulid, $site->ulid);

    $this->postJson($url, ['from_path' => '/dup', 'to_path' => '/x'])->assertCreated();
    $this->postJson($url, ['from_path' => '/dup', 'to_path' => '/y'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('from_path');
});

it('rechaza un status fuera de {301,302}', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = ownerWithSite();
    Sanctum::actingAs($user);

    $this->postJson(redirectsUrl($ws->ulid, $site->ulid), ['from_path' => '/a', 'to_path' => '/b', 'status' => 307])
        ->assertStatus(422)
        ->assertJsonValidationErrors('status');
});

it('actualiza status/is_active y elimina', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = ownerWithSite();
    Sanctum::actingAs($user);
    $url = redirectsUrl($ws->ulid, $site->ulid);

    $id = $this->postJson($url, ['from_path' => '/a', 'to_path' => '/b'])->json('data.id');

    $this->patchJson("{$url}/{$id}", ['status' => 302, 'is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.status', 302)
        ->assertJsonPath('data.is_active', false);

    $this->deleteJson("{$url}/{$id}")->assertNoContent();
    $this->getJson($url)->assertJsonCount(0, 'data');
});

it('en update, cambiar sólo to_path para igualar el from_path guardado es un bucle', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = ownerWithSite();
    Sanctum::actingAs($user);
    $url = redirectsUrl($ws->ulid, $site->ulid);

    $id = $this->postJson($url, ['from_path' => '/misma', 'to_path' => '/otra'])->json('data.id');

    $this->patchJson("{$url}/{$id}", ['to_path' => '/misma'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('to_path');
});

it('editor y viewer no pueden gestionar redirects (RBAC redirect.manage)', function () {
    ['ws' => $ws, 'site' => $site] = ownerWithSite();
    $url = redirectsUrl($ws->ulid, $site->ulid);

    foreach (['editor', 'viewer'] as $role) {
        Sanctum::actingAs(memberWithRole($ws, $role));
        $this->getJson($url)->assertForbidden();
        $this->postJson($url, ['from_path' => '/x', 'to_path' => '/y'])->assertForbidden();
    }
});

it('exige autenticación', function () {
    ['ws' => $ws, 'site' => $site] = ownerWithSite();

    $this->getJson(redirectsUrl($ws->ulid, $site->ulid))->assertUnauthorized();
});

it('aísla los redirects entre sitios del mismo workspace', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $siteA] = ownerWithSite();
    Sanctum::actingAs($user);

    $id = $this->postJson(redirectsUrl($ws->ulid, $siteA->ulid), ['from_path' => '/a', 'to_path' => '/b'])->json('data.id');

    $siteB = withinWorkspace($ws, fn () => Site::factory()->create());

    $this->patchJson(redirectsUrl($ws->ulid, $siteB->ulid)."/{$id}", ['status' => 302])->assertNotFound();
    $this->deleteJson(redirectsUrl($ws->ulid, $siteB->ulid)."/{$id}")->assertNotFound();
});
