<?php

declare(strict_types=1);

use App\Modules\Billing\Infrastructure\Models\Capability;
use App\Modules\Billing\Infrastructure\Models\Plan;
use App\Modules\Content\Infrastructure\Models\Collection;
use App\Modules\Sites\Infrastructure\Models\Site;
use App\Modules\Tenancy\Infrastructure\Models\Workspace;
use Database\Seeders\DatabaseSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(fn () => $this->seed(DatabaseSeeder::class));

function entriesBase(Workspace $ws, Site $site, Collection $collection): string
{
    return "/api/v1/workspaces/{$ws->ulid}/sites/{$site->ulid}/collections/{$collection->ulid}/entries";
}

/** Crea una entrada (borrador) vía API con datos válidos para publicar; devuelve su ULID. */
function newEntry(string $base): string
{
    return test()->postJson($base, [
        'title' => 'Nota editorial',
        'values' => ['excerpt' => 'Un resumen', 'body' => '<p>Cuerpo</p>'],
    ])->json('data.id');
}

it('el redactor envía a revisión y el editor aprueba (Pro)', function () {
    ['user' => $owner, 'ws' => $ws, 'site' => $site, 'articles' => $col] = cmsOwnerContext();
    Sanctum::actingAs($owner);
    $base = entriesBase($ws, $site, $col);
    $id = newEntry($base);

    $this->postJson("{$base}/{$id}/submit-review")->assertOk()->assertJsonPath('data.status', 'in_review');
    $this->postJson("{$base}/{$id}/approve")->assertOk()->assertJsonPath('data.status', 'published');
});

it('aprobar con fecha futura programa la entrada', function () {
    ['user' => $owner, 'ws' => $ws, 'site' => $site, 'articles' => $col] = cmsOwnerContext();
    Sanctum::actingAs($owner);
    $base = entriesBase($ws, $site, $col);
    $id = newEntry($base);
    $this->postJson("{$base}/{$id}/submit-review")->assertOk();

    $this->postJson("{$base}/{$id}/approve", ['publish_at' => now()->addDay()->toIso8601String()])
        ->assertOk()
        ->assertJsonPath('data.status', 'scheduled');
});

it('pedir cambios devuelve a borrador con nota', function () {
    ['user' => $owner, 'ws' => $ws, 'site' => $site, 'articles' => $col] = cmsOwnerContext();
    Sanctum::actingAs($owner);
    $base = entriesBase($ws, $site, $col);
    $id = newEntry($base);
    $this->postJson("{$base}/{$id}/submit-review")->assertOk();

    $this->postJson("{$base}/{$id}/request-changes", ['note' => 'Faltan fuentes'])
        ->assertOk()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.editorial_note', 'Faltan fuentes');
});

it('un rol editor puede enviar pero NO aprobar (403)', function () {
    ['user' => $owner, 'ws' => $ws, 'site' => $site, 'articles' => $col] = cmsOwnerContext();
    Sanctum::actingAs($owner);
    $base = entriesBase($ws, $site, $col);
    $id = newEntry($base);

    $editor = memberWithRole($ws, 'editor');
    Sanctum::actingAs($editor);
    $this->postJson("{$base}/{$id}/submit-review")->assertOk();          // entry.update
    $this->postJson("{$base}/{$id}/approve")->assertForbidden();          // entry.publish falta
});

it('sin publisher.editorial el flujo da 403', function () {
    ['user' => $owner, 'ws' => $ws, 'site' => $site, 'articles' => $col] = cmsOwnerContext();
    $plan = Plan::where('key', 'pro')->firstOrFail();
    $plan->capabilities()->detach(Capability::where('key', 'publisher.editorial')->firstOrFail()->id);

    Sanctum::actingAs($owner);
    $base = entriesBase($ws, $site, $col);
    $id = newEntry($base);

    $this->postJson("{$base}/{$id}/submit-review")->assertForbidden();
});

it('una transición inválida da 422', function () {
    ['user' => $owner, 'ws' => $ws, 'site' => $site, 'articles' => $col] = cmsOwnerContext();
    Sanctum::actingAs($owner);
    $base = entriesBase($ws, $site, $col);
    $id = newEntry($base); // borrador, no en revisión

    $this->postJson("{$base}/{$id}/approve")->assertStatus(422); // aprobar un draft
});
