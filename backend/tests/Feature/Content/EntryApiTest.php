<?php

declare(strict_types=1);

use App\Modules\Content\Infrastructure\Models\Author;
use App\Modules\Content\Infrastructure\Models\Category;
use App\Modules\Content\Infrastructure\Models\Collection;
use App\Modules\Sites\Application\CreateSite;
use Database\Seeders\DatabaseSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

function entriesUrl(string $wsUlid, string $siteUlid, string $collectionUlid): string
{
    return "/api/v1/workspaces/{$wsUlid}/sites/{$siteUlid}/collections/{$collectionUlid}/entries";
}

it('crea una entry en borrador con path calculado', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site, 'articles' => $articles] = cmsOwnerContext();
    Sanctum::actingAs($user);

    $this->postJson(entriesUrl($ws->ulid, $site->ulid, $articles->ulid), [
        'title' => 'Hola Mundo',
        'values' => ['body' => '<p>Contenido</p>', 'reading_time' => 5],
    ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Hola Mundo')
        ->assertJsonPath('data.slug', 'hola-mundo')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.path', '/blog/hola-mundo')
        ->assertJsonPath('data.values.reading_time', 5);
});

it('deriva slugs únicos por colección', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site, 'articles' => $articles] = cmsOwnerContext();
    Sanctum::actingAs($user);
    $url = entriesUrl($ws->ulid, $site->ulid, $articles->ulid);

    $this->postJson($url, ['title' => 'Repetido'])->assertCreated()->assertJsonPath('data.slug', 'repetido');
    $this->postJson($url, ['title' => 'Repetido'])->assertCreated()->assertJsonPath('data.slug', 'repetido-2');
});

it('valida el data contra el schema (perfil draft)', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site, 'articles' => $articles] = cmsOwnerContext();
    Sanctum::actingAs($user);

    // reading_time es integer.
    $this->postJson(entriesUrl($ws->ulid, $site->ulid, $articles->ulid), [
        'title' => 'Malo',
        'values' => ['reading_time' => 'muchísimo'],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('values');
});

it('asigna un autor del sitio y rechaza uno de otro sitio', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site, 'articles' => $articles] = cmsOwnerContext();
    Sanctum::actingAs($user);
    $url = entriesUrl($ws->ulid, $site->ulid, $articles->ulid);

    $author = withinWorkspace($ws, fn () => Author::factory()->create(['site_id' => $site->id]));

    $this->postJson($url, ['title' => 'Con autor', 'author' => $author->ulid])
        ->assertCreated()
        ->assertJsonPath('data.author.id', $author->ulid);

    // Autor de OTRO sitio -> 422.
    $siteB = withinWorkspace($ws, fn () => app(CreateSite::class)->handle(['name' => 'Otro', 'slug' => 'otro']));
    $foreign = withinWorkspace($ws, fn () => Author::factory()->create(['site_id' => $siteB->id]));

    $this->postJson($url, ['title' => 'Autor ajeno', 'author' => $foreign->ulid])
        ->assertStatus(422)
        ->assertJsonValidationErrors('author');
});

it('asigna categorías de la colección y rechaza las de otra colección', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site, 'articles' => $articles] = cmsOwnerContext();
    Sanctum::actingAs($user);
    $url = entriesUrl($ws->ulid, $site->ulid, $articles->ulid);

    // 'general' es la categoría por defecto de articles.
    $general = withinWorkspace($ws, fn () => Category::query()
        ->where('collection_id', $articles->id)->where('slug', 'general')->firstOrFail());

    $this->postJson($url, ['title' => 'Categorizado', 'category_ids' => [$general->ulid]])
        ->assertCreated()
        ->assertJsonPath('data.categories.0.slug', 'general');

    // Categoría de OTRA colección -> 422.
    $other = withinWorkspace($ws, fn () => Collection::factory()->create(['site_id' => $site->id]));
    $foreignCat = withinWorkspace($ws, fn () => Category::factory()->create([
        'site_id' => $site->id, 'collection_id' => $other->id,
    ]));

    $this->postJson($url, ['title' => 'Cat ajena', 'category_ids' => [$foreignCat->ulid]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('category_ids.0');
});

it('lista, muestra y actualiza entries', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site, 'articles' => $articles] = cmsOwnerContext();
    Sanctum::actingAs($user);
    $url = entriesUrl($ws->ulid, $site->ulid, $articles->ulid);

    $ulid = $this->postJson($url, ['title' => 'Original'])->json('data.id');

    $this->getJson($url)->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("{$url}/{$ulid}")->assertOk()->assertJsonPath('data.title', 'Original');

    $this->patchJson("{$url}/{$ulid}", ['title' => 'Editado', 'values' => ['body' => 'nuevo']])
        ->assertOk()
        ->assertJsonPath('data.title', 'Editado')
        ->assertJsonPath('data.values.body', 'nuevo');
});

it('el plan free no puede acceder (capability 403)', function () {
    ['user' => $user, 'workspace' => $ws] = registered('free@example.com');
    $site = withinWorkspace($ws, fn () => app(CreateSite::class)->handle(['name' => 'Blog', 'slug' => 'blog']));
    $articles = withinWorkspace($ws, fn () => Collection::query()
        ->where('site_id', $site->id)->where('handle', 'articles')->firstOrFail());
    Sanctum::actingAs($user);

    $this->getJson(entriesUrl($ws->ulid, $site->ulid, $articles->ulid))->assertForbidden();
});

it('editor crea entries; viewer sólo lee (RBAC)', function () {
    ['ws' => $ws, 'site' => $site, 'articles' => $articles] = cmsOwnerContext();
    $url = entriesUrl($ws->ulid, $site->ulid, $articles->ulid);

    Sanctum::actingAs(memberWithRole($ws, 'editor'));
    $this->postJson($url, ['title' => 'Del editor'])->assertCreated();

    Sanctum::actingAs(memberWithRole($ws, 'viewer'));
    $this->getJson($url)->assertOk();
    $this->postJson($url, ['title' => 'Del viewer'])->assertForbidden();
});

it('aísla entries entre colecciones', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site, 'articles' => $articles] = cmsOwnerContext();
    Sanctum::actingAs($user);

    $ulid = $this->postJson(entriesUrl($ws->ulid, $site->ulid, $articles->ulid), ['title' => 'Sólo articles'])
        ->json('data.id');

    $other = withinWorkspace($ws, fn () => Collection::factory()->create(['site_id' => $site->id]));

    // La entry de 'articles' no se resuelve bajo otra colección (404).
    $this->getJson(entriesUrl($ws->ulid, $site->ulid, $other->ulid)."/{$ulid}")->assertNotFound();
});
