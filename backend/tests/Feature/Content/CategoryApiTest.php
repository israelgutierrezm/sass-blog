<?php

declare(strict_types=1);

use App\Modules\Content\Application\CreateCollection;
use App\Modules\Content\Infrastructure\Models\Collection;
use App\Modules\Sites\Application\CreateSite;
use Database\Seeders\DatabaseSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

function categoriesUrl(string $wsUlid, string $siteUlid, string $collectionUlid): string
{
    return "/api/v1/workspaces/{$wsUlid}/sites/{$siteUlid}/collections/{$collectionUlid}/categories";
}

it('el owner crea y lista categorías de la colección', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site, 'articles' => $articles] = cmsOwnerContext();
    Sanctum::actingAs($user);
    $url = categoriesUrl($ws->ulid, $site->ulid, $articles->ulid);

    $this->postJson($url, ['name' => 'Noticias'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Noticias')
        ->assertJsonPath('data.slug', 'noticias');

    // 'general' por defecto (sembrada) + la nueva.
    $this->getJson($url)->assertOk()->assertJsonCount(2, 'data');
});

it('exige el nombre', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site, 'articles' => $articles] = cmsOwnerContext();
    Sanctum::actingAs($user);

    $this->postJson(categoriesUrl($ws->ulid, $site->ulid, $articles->ulid), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

it('rechaza un slug duplicado dentro de la colección', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site, 'articles' => $articles] = cmsOwnerContext();
    Sanctum::actingAs($user);

    // 'general' ya existe (categoría por defecto).
    $this->postJson(categoriesUrl($ws->ulid, $site->ulid, $articles->ulid), ['name' => 'Otra', 'slug' => 'general'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('slug');
});

it('actualiza y elimina una categoría', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site, 'articles' => $articles] = cmsOwnerContext();
    Sanctum::actingAs($user);
    $url = categoriesUrl($ws->ulid, $site->ulid, $articles->ulid);

    $ulid = $this->postJson($url, ['name' => 'Tutoriales'])->json('data.id');

    $this->patchJson("{$url}/{$ulid}", ['description' => 'Guías paso a paso'])
        ->assertOk()
        ->assertJsonPath('data.description', 'Guías paso a paso');

    $this->deleteJson("{$url}/{$ulid}")->assertNoContent();
    $this->getJson("{$url}/{$ulid}")->assertNotFound();
});

it('el plan free no puede acceder (capability 403)', function () {
    ['user' => $user, 'workspace' => $ws] = registered('free@example.com');
    $site = withinWorkspace($ws, fn () => app(CreateSite::class)->handle(['name' => 'Blog', 'slug' => 'blog']));
    $articles = withinWorkspace($ws, fn () => Collection::query()
        ->where('site_id', $site->id)->where('handle', 'articles')->firstOrFail());
    Sanctum::actingAs($user);

    $this->getJson(categoriesUrl($ws->ulid, $site->ulid, $articles->ulid))->assertForbidden();
});

it('editor gestiona categorías; viewer no (RBAC)', function () {
    ['ws' => $ws, 'site' => $site, 'articles' => $articles] = cmsOwnerContext();
    $url = categoriesUrl($ws->ulid, $site->ulid, $articles->ulid);

    Sanctum::actingAs(memberWithRole($ws, 'editor'));
    $this->getJson($url)->assertOk();
    $this->postJson($url, ['name' => 'Editorial'])->assertCreated();

    Sanctum::actingAs(memberWithRole($ws, 'viewer'));
    $this->getJson($url)->assertForbidden();
});

it('aísla categorías entre colecciones (una categoría no se resuelve bajo otra colección)', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site, 'articles' => $articles] = cmsOwnerContext();
    Sanctum::actingAs($user);

    $ulid = $this->postJson(categoriesUrl($ws->ulid, $site->ulid, $articles->ulid), ['name' => 'Sólo Artículos'])
        ->json('data.id');

    $other = withinWorkspace($ws, fn () => app(CreateCollection::class)->handle($site, ['handle' => 'guides', 'name' => 'Guías']));

    // La categoría de 'articles' no se resuelve bajo 'guides' (404).
    $this->getJson(categoriesUrl($ws->ulid, $site->ulid, $other->ulid)."/{$ulid}")->assertNotFound();
});
