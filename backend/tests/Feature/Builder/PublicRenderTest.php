<?php

declare(strict_types=1);

use App\Modules\Builder\Application\PublishPage;
use App\Modules\Builder\Application\SaveDraft;
use App\Modules\Sites\Infrastructure\Models\Site;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

/** Crea, escribe y publica una página; devuelve el modelo publicado. */
function publishedPage($ws, Site $site, string $path = '/', string $heading = 'Público')
{
    $page = makePage($ws, $site, 'Home', $path);

    return withinWorkspace($ws, function () use ($page, $heading) {
        app(SaveDraft::class)->handle($page, heroSchema($heading));

        return app(PublishPage::class)->handle($page->fresh());
    });
}

it('renderiza una página publicada por su path (sin auth)', function () {
    ['ws' => $ws, 'site' => $site] = ownerWithSite();
    publishedPage($ws, $site, '/', 'Hola Público');

    $this->getJson("/api/v1/public/sites/{$site->ulid}/render?path=/")
        ->assertOk()
        ->assertJsonPath('data.page.path', '/')
        ->assertJsonPath('data.seo.robots', 'index,follow')
        ->assertJsonPath('data.page.sections.0.props.heading', 'Hola Público');
});

it('404 si la página no está publicada', function () {
    ['ws' => $ws, 'site' => $site] = ownerWithSite();
    makePage($ws, $site, 'Home', '/'); // sólo draft

    $this->getJson("/api/v1/public/sites/{$site->ulid}/render?path=/")->assertNotFound();
});

it('no filtra páginas entre sitios', function () {
    ['ws' => $ws, 'site' => $siteA] = ownerWithSite();
    $siteB = withinWorkspace($ws, fn () => Site::factory()->create());
    publishedPage($ws, $siteA, '/', 'Sólo A');

    // siteB no tiene página publicada en '/'
    $this->getJson("/api/v1/public/sites/{$siteB->ulid}/render?path=/")->assertNotFound();
});

it('404 ante un ULID de sitio inexistente', function () {
    $this->getJson('/api/v1/public/sites/01ARZ3NDEKTSV4RRFFQ69G5FAV/render?path=/')->assertNotFound();
});

it('el preview firmado del draft funciona; sin firma da 403', function () {
    ['user' => $u, 'ws' => $ws, 'site' => $site] = ownerWithSite();
    $page = makePage($ws, $site);
    withinWorkspace($ws, fn () => app(SaveDraft::class)->handle($page, heroSchema('Borrador')));

    Sanctum::actingAs($u);
    $signed = $this->postJson("/api/v1/workspaces/{$ws->ulid}/sites/{$site->ulid}/pages/{$page->ulid}/preview-link")
        ->json('url');

    // La URL firmada funciona sin auth y marca noindex.
    $this->getJson($signed)
        ->assertOk()
        ->assertJsonPath('data.seo.robots', 'noindex,nofollow');

    // Sin firma → 403.
    $this->getJson("/api/v1/public/sites/{$site->ulid}/pages/{$page->ulid}/preview")
        ->assertForbidden();
});

it('las rutas /public/* no llevan middleware de autenticación', function () {
    $publicRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => str_starts_with($r->uri(), 'api/v1/public/'));

    expect($publicRoutes)->not->toBeEmpty();

    foreach ($publicRoutes as $route) {
        $middleware = $route->gatherMiddleware();
        expect(collect($middleware)->contains(fn ($m) => str_starts_with((string) $m, 'auth')))->toBeFalse();
        expect($middleware)->not->toContain('workspace');
    }
});
