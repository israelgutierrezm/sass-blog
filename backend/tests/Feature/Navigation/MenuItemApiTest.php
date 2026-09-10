<?php

declare(strict_types=1);

use Database\Seeders\DatabaseSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

/** Owner Pro + un menú `primary` recién creado; devuelve [ws, site, articles, menuId]. */
function menuContext(): array
{
    ['user' => $user, 'ws' => $ws, 'site' => $site, 'articles' => $articles] = cmsOwnerContext();
    Sanctum::actingAs($user);
    $menuId = test()->postJson(menusUrl($ws->ulid, $site->ulid), ['handle' => 'primary', 'name' => 'Principal'])->json('data.id');

    return [$ws, $site, $articles, $menuId];
}

function itemsUrl(string $wsUlid, string $siteUlid, string $menuId): string
{
    return menusUrl($wsUlid, $siteUlid)."/{$menuId}/items";
}

it('añade un ítem url y un ítem home', function () {
    [$ws, $site, , $menuId] = menuContext();
    $url = itemsUrl($ws->ulid, $site->ulid, $menuId);

    $this->postJson($url, ['label' => 'Blog externo', 'link_type' => 'url', 'url' => 'https://ejemplo.com'])
        ->assertCreated()
        ->assertJsonPath('data.link_type', 'url')
        ->assertJsonPath('data.url', 'https://ejemplo.com');

    $this->postJson($url, ['label' => 'Inicio', 'link_type' => 'home'])->assertCreated();
});

it('añade un ítem que enlaza a una página existente del sitio', function () {
    [$ws, $site, , $menuId] = menuContext();
    $page = makePage($ws, $site, 'Acerca', '/acerca');

    $this->postJson(itemsUrl($ws->ulid, $site->ulid, $menuId), [
        'label' => 'Acerca', 'link_type' => 'page', 'target' => $page->ulid,
    ])
        ->assertCreated()
        ->assertJsonPath('data.link_type', 'page')
        ->assertJsonPath('data.target', $page->ulid);
});

it('enlaza a una colección existente', function () {
    [$ws, $site, $articles, $menuId] = menuContext();

    $this->postJson(itemsUrl($ws->ulid, $site->ulid, $menuId), [
        'label' => 'Blog', 'link_type' => 'collection', 'target' => $articles->ulid,
    ])->assertCreated();
});

it('rechaza un destino inexistente para un enlace de página', function () {
    [$ws, $site, , $menuId] = menuContext();

    $this->postJson(itemsUrl($ws->ulid, $site->ulid, $menuId), [
        'label' => 'Rota', 'link_type' => 'page', 'target' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('target');
});

it('rechaza un ítem url que incluye un destino', function () {
    [$ws, $site, , $menuId] = menuContext();

    $this->postJson(itemsUrl($ws->ulid, $site->ulid, $menuId), [
        'label' => 'X', 'link_type' => 'url', 'url' => 'https://x.com', 'target' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('target');
});

it('anida un hijo bajo un padre y el árbol lo refleja', function () {
    [$ws, $site, , $menuId] = menuContext();
    $url = itemsUrl($ws->ulid, $site->ulid, $menuId);

    $parentId = $this->postJson($url, ['label' => 'Padre', 'link_type' => 'home'])->json('data.id');
    $this->postJson($url, ['label' => 'Hijo', 'link_type' => 'url', 'url' => '/hijo', 'parent' => $parentId])->assertCreated();

    $tree = $this->getJson(menusUrl($ws->ulid, $site->ulid)."/{$menuId}")->assertOk()->json('data.items');
    expect($tree)->toHaveCount(1)
        ->and($tree[0]['label'])->toBe('Padre')
        ->and($tree[0]['children'])->toHaveCount(1)
        ->and($tree[0]['children'][0]['label'])->toBe('Hijo');
});

it('rechaza un padre de otro menú', function () {
    [$ws, $site, , $menuId] = menuContext();
    $otherMenu = $this->postJson(menusUrl($ws->ulid, $site->ulid), ['handle' => 'footer', 'name' => 'Pie'])->json('data.id');
    $foreignParent = $this->postJson(itemsUrl($ws->ulid, $site->ulid, $otherMenu), ['label' => 'Ajeno', 'link_type' => 'home'])->json('data.id');

    $this->postJson(itemsUrl($ws->ulid, $site->ulid, $menuId), [
        'label' => 'X', 'link_type' => 'home', 'parent' => $foreignParent,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('parent');
});

it('rechaza un ciclo al re-anidar un ítem dentro de su propio hijo', function () {
    [$ws, $site, , $menuId] = menuContext();
    $url = itemsUrl($ws->ulid, $site->ulid, $menuId);

    $a = $this->postJson($url, ['label' => 'A', 'link_type' => 'home'])->json('data.id');
    $b = $this->postJson($url, ['label' => 'B', 'link_type' => 'home', 'parent' => $a])->json('data.id');

    // Intentar poner A dentro de B (su hijo) → ciclo.
    $this->patchJson("{$url}/{$a}", ['parent' => $b])
        ->assertStatus(422)
        ->assertJsonValidationErrors('parent');
});

it('actualiza etiqueta/posición y borra en cascada los hijos', function () {
    [$ws, $site, , $menuId] = menuContext();
    $url = itemsUrl($ws->ulid, $site->ulid, $menuId);

    $parent = $this->postJson($url, ['label' => 'Padre', 'link_type' => 'home'])->json('data.id');
    $this->postJson($url, ['label' => 'Hijo', 'link_type' => 'home', 'parent' => $parent])->assertCreated();

    $this->patchJson("{$url}/{$parent}", ['label' => 'Renombrado', 'position' => 5])
        ->assertOk()
        ->assertJsonPath('data.label', 'Renombrado')
        ->assertJsonPath('data.position', 5);

    // Borrar el padre arrastra al hijo: el árbol queda vacío.
    $this->deleteJson("{$url}/{$parent}")->assertNoContent();
    expect($this->getJson(menusUrl($ws->ulid, $site->ulid)."/{$menuId}")->json('data.items'))->toHaveCount(0);
});
