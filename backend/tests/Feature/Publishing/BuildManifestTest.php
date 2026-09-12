<?php

declare(strict_types=1);

use App\Modules\Builder\Application\PublishPage;
use App\Modules\Builder\Application\SaveDraft;
use App\Modules\Publishing\Application\BuildManifest;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

/** Schema con un hero + un `seo` opcional. */
function heroPageSchema(string $heading, ?array $seo = null): array
{
    return array_filter([
        'schema_version' => 1,
        'seo' => $seo,
        'sections' => [[
            'id' => Str::upper((string) Str::ulid()),
            'type' => 'hero', 'variant' => 'hero-centered', 'visible' => true,
            'props' => ['heading' => $heading],
            'settings' => ['spacing' => ['top' => 'md', 'bottom' => 'md']],
        ]],
    ], fn ($v) => $v !== null);
}

function publishSchema($ws, $site, string $path, array $schema): void
{
    $page = makePage($ws, $site, 'P', $path);
    withinWorkspace($ws, function () use ($page, $schema) {
        app(SaveDraft::class)->handle($page, $schema);
        app(PublishPage::class)->handle($page->fresh());
    });
}

it('compone el manifest: enumera lo publicado, arma payloads y recolecta media', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site, 'articles' => $articles] = cmsOwnerContext();
    Sanctum::actingAs($user);

    $ogImage = 'http://localhost/storage/media/hero.jpg';
    publishSchema($ws, $site, '/', heroPageSchema('Inicio', ['og_image' => $ogImage]));
    publishSchema($ws, $site, '/acerca', heroPageSchema('Acerca'));
    makePage($ws, $site, 'Oculta', '/oculta'); // sólo borrador → excluida
    [, $slug] = publishedArticle($ws->ulid, $site->ulid, $articles->ulid);

    $manifest = withinWorkspace($ws, fn () => app(BuildManifest::class)->forSite($site));

    // Sitio
    expect($manifest['site']['ulid'])->toBe($site->ulid);

    // Enumeración: '/', '/acerca', '/blog/{slug}'; NO '/oculta'.
    $paths = collect($manifest['pages'])->pluck('path')->all();
    expect($paths)->toContain('/', '/acerca', "/blog/{$slug}")
        ->and($paths)->not->toContain('/oculta')
        ->and($manifest['pages'])->toHaveCount(3);

    // Cada página trae su payload de render con el path y el seo correctos.
    $home = collect($manifest['pages'])->firstWhere('path', '/');
    expect($home['render']['page']['path'])->toBe('/')
        ->and($home['render']['seo']['og_image'])->toBe($ogImage);

    // El detalle dinámico se resolvió (entry embebida).
    $article = collect($manifest['pages'])->firstWhere('path', "/blog/{$slug}");
    expect($article['render']['entry']['slug'])->toBe($slug);

    // Media referenciada recolectada.
    expect($manifest['media'])->toContain($ogImage);
});

it('un sitio sin nada publicado da un manifest vacío de páginas', function () {
    ['ws' => $ws, 'site' => $site] = ownerWithSite();

    $manifest = withinWorkspace($ws, fn () => app(BuildManifest::class)->forSite($site));

    expect($manifest['pages'])->toBe([])
        ->and($manifest['media'])->toBe([]);
});
