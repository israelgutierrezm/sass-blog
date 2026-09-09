<?php

declare(strict_types=1);

use App\Modules\Builder\Application\CreatePage;
use App\Modules\Builder\Application\PublishPage;
use App\Modules\Builder\Application\SaveDraft;
use App\Modules\Content\Infrastructure\Models\Entry;
use Database\Seeders\DatabaseSeeder;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

it('el render estático emite el seo del schema (override)', function () {
    ['ws' => $ws, 'site' => $site, 'user' => $user] = ownerWithSite();

    $schema = array_merge(heroSchema(), [
        'seo' => [
            'meta_title' => 'Título SEO',
            'meta_description' => 'Descripción SEO',
            'robots' => 'noindex,follow',
            'og_image' => 'https://cdn.example.com/x.png',
        ],
    ]);

    withinWorkspace($ws, function () use ($site, $schema, $user) {
        $page = app(CreatePage::class)->handle($site, 'Página', '/', $user->id);
        app(SaveDraft::class)->handle($page, $schema);
        app(PublishPage::class)->handle($page, $user->id);
    });

    $this->getJson("/api/v1/public/sites/{$site->ulid}/render?path=/")
        ->assertOk()
        ->assertJsonPath('data.seo.title', 'Título SEO')
        ->assertJsonPath('data.seo.description', 'Descripción SEO')
        ->assertJsonPath('data.seo.robots', 'noindex,follow')
        ->assertJsonPath('data.seo.og_image', 'https://cdn.example.com/x.png');
});

it('el render estático deriva el seo cuando el schema no lo trae', function () {
    ['ws' => $ws, 'site' => $site, 'user' => $user] = ownerWithSite();

    withinWorkspace($ws, function () use ($site, $user) {
        $page = app(CreatePage::class)->handle($site, 'Mi Título', '/', $user->id);
        app(SaveDraft::class)->handle($page, heroSchema());
        app(PublishPage::class)->handle($page, $user->id);
    });

    $this->getJson("/api/v1/public/sites/{$site->ulid}/render?path=/")
        ->assertOk()
        ->assertJsonPath('data.seo.title', 'Mi Título')
        ->assertJsonPath('data.seo.description', null)
        ->assertJsonPath('data.seo.robots', 'index,follow');
});

it('el render dinámico deriva el seo de la entry (excerpt, featured_image, Article)', function () {
    ['ws' => $ws, 'site' => $site, 'articles' => $articles] = cmsOwnerContext();

    $slug = withinWorkspace($ws, function () use ($site, $articles) {
        $entry = Entry::factory()->published()->create([
            'site_id' => $site->id,
            'collection_id' => $articles->id,
            'title' => 'Artículo',
            'data' => ['body' => 'x', 'excerpt' => 'Resumen del artículo', 'featured_image' => 'https://cdn.example.com/f.png'],
        ]);

        return $entry->slug;
    });

    $this->getJson("/api/v1/public/sites/{$site->ulid}/render?path=/blog/{$slug}")
        ->assertOk()
        ->assertJsonPath('data.seo.title', 'Artículo')
        ->assertJsonPath('data.seo.description', 'Resumen del artículo')
        ->assertJsonPath('data.seo.og_image', 'https://cdn.example.com/f.png')
        ->assertJsonPath('data.seo.jsonld_type', 'Article');
});
