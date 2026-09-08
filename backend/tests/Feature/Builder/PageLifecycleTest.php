<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Infrastructure\Models\AuditLog;
use App\Modules\Builder\Application\CreatePage;
use App\Modules\Builder\Application\PublishPage;
use App\Modules\Builder\Application\SaveDraft;
use App\Modules\Builder\Events\PagePublished;
use App\Modules\Builder\Infrastructure\Models\Page;
use App\Modules\Builder\Infrastructure\Models\PageVersion;
use App\Modules\Sites\Infrastructure\Models\Site;
use App\Modules\Tenancy\Infrastructure\Models\Workspace;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * @return array{0: Workspace, 1: Site}
 */
function builderSite(): array
{
    $ws = Workspace::factory()->create(['owner_id' => User::factory()->create()->id]);
    $site = withinWorkspace($ws, fn () => Site::factory()->create());

    return [$ws, $site];
}

/** @return array<string, mixed> */
function heroSchema(string $heading = 'Hola Mundo'): array
{
    return [
        'schema_version' => 1,
        'sections' => [[
            'id' => Str::upper((string) Str::ulid()),
            'type' => 'hero',
            'variant' => 'hero-centered',
            'visible' => true,
            'props' => ['heading' => $heading],
            'settings' => [],
        ]],
    ];
}

it('crea una página con su draft v1 y fija el puntero', function () {
    [$ws, $site] = builderSite();
    actingForWorkspace($ws);

    $page = app(CreatePage::class)->handle($site, 'Inicio', '/');

    expect($page->workspace_id)->toBe($ws->id)
        ->and($page->site_id)->toBe($site->id)
        ->and($page->status)->toBe(Page::STATUS_DRAFT)
        ->and($page->draft_version_id)->not->toBeNull()
        ->and($page->published_version_id)->toBeNull()
        ->and($page->ulid)->toHaveLength(26);

    $draft = $page->draftVersion;
    expect($draft->version_number)->toBe(1)
        ->and($draft->status)->toBe(PageVersion::STATUS_DRAFT)
        ->and($draft->schema)->toEqual(['schema_version' => 1, 'sections' => []]);
});

it('guarda el schema del draft in situ', function () {
    [$ws, $site] = builderSite();
    actingForWorkspace($ws);
    $page = app(CreatePage::class)->handle($site, 'Landing', '/landing');

    $schema = heroSchema('Bienvenido');
    app(SaveDraft::class)->handle($page, $schema);

    // toEqual (no toBe): MySQL JSON reordena las claves de objeto; el orden del
    // array `sections` (que sí importa para el render) sí se preserva.
    expect($page->draftVersion()->first()->schema)->toEqual($schema);
});

it('publica: congela el draft, bifurca v2, rota punteros y audita', function () {
    [$ws, $site] = builderSite();
    actingForWorkspace($ws);
    $page = app(CreatePage::class)->handle($site, 'Home', '/');
    $originalDraftId = $page->draft_version_id;

    $page = app(PublishPage::class)->handle($page, $ws->owner_id);

    expect($page->status)->toBe(Page::STATUS_PUBLISHED)
        ->and($page->published_version_id)->toBe($originalDraftId)
        ->and($page->draft_version_id)->not->toBe($originalDraftId)
        ->and($page->published_at)->not->toBeNull();

    $frozen = PageVersion::find($originalDraftId);
    expect($frozen->status)->toBe(PageVersion::STATUS_PUBLISHED)
        ->and($frozen->published_at)->not->toBeNull()
        ->and($frozen->published_by)->toBe($ws->owner_id);

    $newDraft = $page->draftVersion;
    expect($newDraft->version_number)->toBe(2)
        ->and($newDraft->status)->toBe(PageVersion::STATUS_DRAFT);

    expect(AuditLog::where('action', 'page.published')
        ->where('entity_id', (string) $page->id)->exists())->toBeTrue();
});

it('emite PagePublished con el payload correcto', function () {
    Event::fake([PagePublished::class]);
    [$ws, $site] = builderSite();
    actingForWorkspace($ws);
    $page = app(CreatePage::class)->handle($site, 'Home', '/');

    app(PublishPage::class)->handle($page, $ws->owner_id);

    Event::assertDispatched(PagePublished::class, fn (PagePublished $e) => $e->pageId === $page->id
        && $e->workspaceId === $ws->id
        && $e->siteId === $site->id
        && $e->publishedBy === $ws->owner_id);
});

it('la versión publicada es inmutable (no admite UPDATE ni DELETE)', function () {
    [$ws, $site] = builderSite();
    actingForWorkspace($ws);
    $page = app(CreatePage::class)->handle($site, 'Home', '/');
    app(PublishPage::class)->handle($page, $ws->owner_id);

    $frozen = PageVersion::find($page->fresh()->published_version_id);
    $frozen->label = 'intento de cambio';

    expect(fn () => $frozen->save())->toThrow(RuntimeException::class);
    expect(fn () => PageVersion::find($frozen->id)->delete())->toThrow(RuntimeException::class);
});

it('republicar crea una nueva versión sin mutar la anterior', function () {
    [$ws, $site] = builderSite();
    actingForWorkspace($ws);
    $page = app(CreatePage::class)->handle($site, 'Home', '/');

    app(PublishPage::class)->handle($page, $ws->owner_id);
    $v1 = PageVersion::where('page_id', $page->id)->where('version_number', 1)->first();
    $v1Schema = $v1->schema;

    app(SaveDraft::class)->handle($page->fresh(), heroSchema('Segunda'));
    app(PublishPage::class)->handle($page->fresh(), $ws->owner_id);

    expect(PageVersion::where('page_id', $page->id)->count())->toBe(3)
        ->and($v1->fresh()->schema)->toEqual($v1Schema)
        ->and($page->fresh()->draftVersion->version_number)->toBe(3);
});

it('aísla las páginas por workspace', function () {
    [$wsA, $siteA] = builderSite();
    [$wsB] = builderSite();

    withinWorkspace($wsA, fn () => app(CreatePage::class)->handle($siteA, 'A', '/a'));

    actingForWorkspace($wsB);
    expect(Page::count())->toBe(0);

    actingForWorkspace($wsA);
    expect(Page::count())->toBe(1);
});

it('exige path único por site', function () {
    [$ws, $site] = builderSite();
    actingForWorkspace($ws);
    app(CreatePage::class)->handle($site, 'Uno', '/dup');

    expect(fn () => app(CreatePage::class)->handle($site, 'Dos', '/dup'))
        ->toThrow(QueryException::class);
});
