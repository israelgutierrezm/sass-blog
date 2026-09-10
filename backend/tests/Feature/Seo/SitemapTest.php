<?php

declare(strict_types=1);

use App\Modules\Sites\Application\CreateSite;
use Database\Seeders\DatabaseSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

function sitemapUrl(string $siteUlid): string
{
    return "/api/v1/public/sites/{$siteUlid}/sitemap.xml";
}

it('el sitemap lista páginas y artículos publicados con <loc> absolutas', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site, 'articles' => $articles] = cmsOwnerContext();
    Sanctum::actingAs($user);
    withinWorkspace($ws, fn () => $site->update(['settings' => ['base_url' => 'https://mi-sitio.com']]));

    publishedPage($ws, $site, '/acerca', 'Acerca');   // página publicada
    makePage($ws, $site, 'Oculta', '/oculta');         // sólo borrador
    [, $slug] = publishedArticle($ws->ulid, $site->ulid, $articles->ulid);

    $body = $this->get(sitemapUrl($site->ulid))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->getContent();

    expect($body)
        ->toContain('<loc>https://mi-sitio.com/acerca</loc>')
        ->toContain("<loc>https://mi-sitio.com/blog/{$slug}</loc>")
        ->not->toContain('/oculta');                   // el borrador no aparece
});

it('el sitemap es XML público sin autenticación', function () {
    ['ws' => $ws, 'site' => $site] = ownerWithSite();
    publishedPage($ws, $site, '/', 'Home');

    // Sin actingAs.
    $this->get(sitemapUrl($site->ulid))
        ->assertOk()
        ->assertSee('<urlset', false);
});

it('robots.txt referencia el sitemap y es público', function () {
    ['ws' => $ws, 'site' => $site] = ownerWithSite();
    withinWorkspace($ws, fn () => $site->update(['settings' => ['base_url' => 'https://mi-sitio.com']]));

    $this->get("/api/v1/public/sites/{$site->ulid}/robots.txt")
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('Sitemap: https://mi-sitio.com/sitemap.xml', false)
        ->assertSee('User-agent: *', false);
});

it('aísla el sitemap entre sitios (el artículo de A no aparece en B)', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $siteA, 'articles' => $articlesA] = cmsOwnerContext();
    Sanctum::actingAs($user);
    [, $slug] = publishedArticle($ws->ulid, $siteA->ulid, $articlesA->ulid);

    $siteB = withinWorkspace($ws, fn () => app(CreateSite::class)->handle(['name' => 'Otro', 'slug' => 'otro']));

    expect($this->get(sitemapUrl($siteB->ulid))->assertOk()->getContent())
        ->not->toContain($slug);
});

it('404 para un sitio inexistente', function () {
    $this->get('/api/v1/public/sites/01ARZ3NDEKTSV4RRFFQ69G5FAV/sitemap.xml')->assertNotFound();
});
