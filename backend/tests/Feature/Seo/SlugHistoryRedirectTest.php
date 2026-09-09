<?php

declare(strict_types=1);

use App\Modules\Seo\Infrastructure\Models\Redirect;
use Database\Seeders\DatabaseSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

it('cambiar el path de una página publicada auto-crea un redirect 301 del viejo al nuevo', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = ownerWithSite();
    $page = publishedPage($ws, $site, '/vieja', 'Contenido');
    Sanctum::actingAs($user);

    $this->patchJson(pagesUrl($ws, $site)."/{$page->ulid}", ['path' => '/nueva'])->assertOk();

    $redirect = withinWorkspace($ws, fn () => Redirect::where('from_path', '/vieja')->first());
    expect($redirect)->not->toBeNull()
        ->and($redirect->to_path)->toBe('/nueva')
        ->and($redirect->status)->toBe(301)
        ->and($redirect->source)->toBe(Redirect::SOURCE_SLUG_CHANGE);

    // Y el render lo resuelve: la URL vieja emite 301 a la nueva.
    $this->getJson("/api/v1/public/sites/{$site->ulid}/render?path=/vieja")
        ->assertOk()
        ->assertJsonPath('data.redirect.to', '/nueva');
});

it('no crea redirect si la página no estaba publicada (URL vieja nunca fue pública)', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = ownerWithSite();
    $page = makePage($ws, $site, 'Borrador', '/vieja'); // sólo draft
    Sanctum::actingAs($user);

    $this->patchJson(pagesUrl($ws, $site)."/{$page->ulid}", ['path' => '/nueva'])->assertOk();

    withinWorkspace($ws, fn () => expect(Redirect::count())->toBe(0));
});

it('no crea redirect si cambia el título pero no el path', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = ownerWithSite();
    $page = publishedPage($ws, $site, '/estable', 'Contenido');
    Sanctum::actingAs($user);

    $this->patchJson(pagesUrl($ws, $site)."/{$page->ulid}", ['title' => 'Otro título'])->assertOk();

    withinWorkspace($ws, fn () => expect(Redirect::count())->toBe(0));
});

it('cambiar el slug de un artículo publicado auto-crea el redirect y el render lo resuelve', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site, 'articles' => $articles] = cmsOwnerContext();
    Sanctum::actingAs($user);

    [$id, $slug] = publishedArticle($ws->ulid, $site->ulid, $articles->ulid);
    $base = "/api/v1/workspaces/{$ws->ulid}/sites/{$site->ulid}/collections/{$articles->ulid}/entries";

    $this->patchJson("{$base}/{$id}", ['slug' => 'nuevo-slug'])->assertOk();

    $redirect = withinWorkspace($ws, fn () => Redirect::where('from_path', "/blog/{$slug}")->first());
    expect($redirect)->not->toBeNull()
        ->and($redirect->to_path)->toBe('/blog/nuevo-slug')
        ->and($redirect->source)->toBe(Redirect::SOURCE_SLUG_CHANGE);

    $this->getJson("/api/v1/public/sites/{$site->ulid}/render?path=/blog/{$slug}")
        ->assertOk()
        ->assertJsonPath('data.redirect.to', '/blog/nuevo-slug');
});

it('en un round-trip A→B→A no queda ciclo: la URL vieja apunta a la viva', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = ownerWithSite();
    $page = publishedPage($ws, $site, '/a', 'Contenido');
    Sanctum::actingAs($user);
    $url = pagesUrl($ws, $site)."/{$page->ulid}";

    $this->patchJson($url, ['path' => '/b'])->assertOk(); // a→b
    $this->patchJson($url, ['path' => '/a'])->assertOk(); // vuelve a /a: se limpia a→b, se crea b→a

    // /a es de nuevo una página viva.
    $this->getJson("/api/v1/public/sites/{$site->ulid}/render?path=/a")
        ->assertOk()
        ->assertJsonPath('data.page.path', '/a');

    // /b redirige a /a (sin ciclo → sin 404).
    $this->getJson("/api/v1/public/sites/{$site->ulid}/render?path=/b")
        ->assertOk()
        ->assertJsonPath('data.redirect.to', '/a');

    // No se acumularon filas: sólo b→a.
    withinWorkspace($ws, fn () => expect(Redirect::count())->toBe(1));
});
