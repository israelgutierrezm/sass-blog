<?php

declare(strict_types=1);

use App\Modules\Builder\Application\PublishPage;
use App\Modules\Builder\Application\SaveDraft;
use App\Modules\Publishing\Application\StaticRenderer;
use App\Modules\Publishing\Infrastructure\Models\Deployment;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeStaticRenderer;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
    Storage::fake('public');
    Storage::fake('local');
    app()->bind(StaticRenderer::class, FakeStaticRenderer::class); // sin Node en tests
});

function zipNames(string $absPath): array
{
    $zip = new ZipArchive;
    $zip->open($absPath);
    $names = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $names[] = $zip->getNameIndex($i);
    }
    $zip->close();

    return $names;
}

it('ensambla el artefacto: HTML + styles + sitemap/robots + media copiada y reescrita', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = cmsOwnerContext();
    Sanctum::actingAs($user);
    Storage::disk('public')->put('media/hero.jpg', 'JPEGBYTES');
    $ogImage = 'http://localhost/storage/media/hero.jpg';

    // Página '/' publicada con og_image de media.
    $page = makePage($ws, $site, 'Inicio', '/');
    withinWorkspace($ws, function () use ($page, $ogImage) {
        app(SaveDraft::class)->handle($page, [
            'schema_version' => 1,
            'seo' => ['og_image' => $ogImage],
            'sections' => [[
                'id' => Str::upper((string) Str::ulid()),
                'type' => 'hero', 'variant' => 'hero-centered', 'visible' => true,
                'props' => ['heading' => 'Hola'], 'settings' => ['spacing' => ['top' => 'md', 'bottom' => 'md']],
            ]],
        ]);
        app(PublishPage::class)->handle($page->fresh());
    });

    $url = deploymentsUrl($ws->ulid, $site->ulid);
    $id = $this->postJson($url)->assertStatus(202)->json('data.id');

    // Cola sync: el build corrió con el renderer fake → success + artefacto.
    $this->getJson("{$url}/{$id}")->assertOk()
        ->assertJsonPath('data.status', 'success')
        ->assertJsonPath('data.has_artifact', true);

    $ref = withinWorkspace($ws, fn () => Deployment::findByUlid($id)->artifact_ref);
    $names = zipNames(Storage::disk('local')->path($ref));

    expect($names)->toContain('index.html', 'assets/styles.css', 'sitemap.xml', 'robots.txt', 'assets/media/hero.jpg');

    // El HTML reescribió la URL de media a la ruta relativa del artefacto.
    $zip = new ZipArchive;
    $zip->open(Storage::disk('local')->path($ref));
    $home = $zip->getFromName('index.html');
    $zip->close();
    expect($home)->toContain('src="assets/media/hero.jpg"')
        ->and($home)->not->toContain($ogImage);
});

it('descarga el artefacto (ZIP)', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = cmsOwnerContext();
    Sanctum::actingAs($user);
    publishedPage($ws, $site, '/', 'Inicio');

    $url = deploymentsUrl($ws->ulid, $site->ulid);
    $id = $this->postJson($url)->json('data.id');

    $this->get("{$url}/{$id}/download")
        ->assertOk()
        ->assertHeader('content-disposition', "attachment; filename=site-{$site->ulid}.zip");
});

it('es idempotente: re-disparar con el mismo estado devuelve el artefacto existente', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = cmsOwnerContext();
    Sanctum::actingAs($user);
    publishedPage($ws, $site, '/', 'Inicio');
    $url = deploymentsUrl($ws->ulid, $site->ulid);

    $first = $this->postJson($url)->assertStatus(202)->json('data.id');
    // Sin cambios publicados → 200 con el mismo deployment, sin reconstruir.
    $second = $this->postJson($url)->assertStatus(200)->json('data.id');

    expect($second)->toBe($first);
    $this->getJson($url)->assertJsonCount(1, 'data');
});
