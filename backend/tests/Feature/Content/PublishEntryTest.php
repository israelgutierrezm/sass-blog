<?php

declare(strict_types=1);

use App\Modules\Audit\Infrastructure\Models\AuditLog;
use App\Modules\Content\Events\EntryPublished;
use App\Modules\Content\Infrastructure\Models\Entry;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

/** Crea una entry en borrador con el body requerido presente (lista para publicar). */
function draftEntryUlid(string $wsUlid, string $siteUlid, string $collectionUlid): string
{
    return test()->postJson(
        "/api/v1/workspaces/{$wsUlid}/sites/{$siteUlid}/collections/{$collectionUlid}/entries",
        ['title' => 'Publicable', 'values' => ['body' => '<p>Contenido</p>']],
    )->json('data.id');
}

it('el owner publica una entry válida y queda registrada en auditoría', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site, 'articles' => $articles] = cmsOwnerContext();
    Sanctum::actingAs($user);
    $base = "/api/v1/workspaces/{$ws->ulid}/sites/{$site->ulid}/collections/{$articles->ulid}/entries";

    $ulid = draftEntryUlid($ws->ulid, $site->ulid, $articles->ulid);

    $this->postJson("{$base}/{$ulid}/publish")
        ->assertOk()
        ->assertJsonPath('data.status', 'published');

    $entry = withinWorkspace($ws, fn () => Entry::findByUlid($ulid));
    expect($entry->status)->toBe('published')
        ->and($entry->published_at)->not->toBeNull();

    expect(AuditLog::where('action', 'entry.published')
        ->where('entity_type', 'entry')
        ->where('entity_id', (string) $entry->id)
        ->exists())->toBeTrue();
});

it('no publica si falta un campo required (revalida perfil publish)', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site, 'articles' => $articles] = cmsOwnerContext();
    Sanctum::actingAs($user);
    $base = "/api/v1/workspaces/{$ws->ulid}/sites/{$site->ulid}/collections/{$articles->ulid}/entries";

    // Sin body (draft lo permite; publish no).
    $ulid = $this->postJson($base, ['title' => 'Incompleta'])->json('data.id');

    $this->postJson("{$base}/{$ulid}/publish")
        ->assertStatus(422)
        ->assertJsonValidationErrors('values');

    // Sigue en borrador.
    $entry = withinWorkspace($ws, fn () => Entry::findByUlid($ulid));
    expect($entry->status)->toBe('draft')
        ->and($entry->published_at)->toBeNull();
});

it('editor y viewer no pueden publicar (RBAC); owner sí', function () {
    ['user' => $owner, 'ws' => $ws, 'site' => $site, 'articles' => $articles] = cmsOwnerContext();
    $base = "/api/v1/workspaces/{$ws->ulid}/sites/{$site->ulid}/collections/{$articles->ulid}/entries";

    Sanctum::actingAs($owner);
    $ulid = draftEntryUlid($ws->ulid, $site->ulid, $articles->ulid);

    Sanctum::actingAs(memberWithRole($ws, 'editor'));
    $this->postJson("{$base}/{$ulid}/publish")->assertForbidden();

    Sanctum::actingAs(memberWithRole($ws, 'viewer'));
    $this->postJson("{$base}/{$ulid}/publish")->assertForbidden();

    Sanctum::actingAs($owner);
    $this->postJson("{$base}/{$ulid}/publish")->assertOk();
});

it('emite el evento EntryPublished al publicar', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site, 'articles' => $articles] = cmsOwnerContext();
    Sanctum::actingAs($user);
    $base = "/api/v1/workspaces/{$ws->ulid}/sites/{$site->ulid}/collections/{$articles->ulid}/entries";

    $ulid = draftEntryUlid($ws->ulid, $site->ulid, $articles->ulid);
    $entry = withinWorkspace($ws, fn () => Entry::findByUlid($ulid));

    Event::fake([EntryPublished::class]);

    $this->postJson("{$base}/{$ulid}/publish")->assertOk();

    Event::assertDispatched(EntryPublished::class, fn (EntryPublished $e) => $e->entryId === $entry->id
        && $e->siteId === $site->id
        && $e->collectionId === $articles->id
        && $e->publishedBy === $user->id);
});
