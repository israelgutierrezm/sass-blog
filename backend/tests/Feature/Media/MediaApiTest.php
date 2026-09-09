<?php

declare(strict_types=1);

use App\Modules\Sites\Application\CreateSite;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
    Storage::fake('public');
});

function mediaUrl(string $wsUlid, string $siteUlid): string
{
    return "/api/v1/workspaces/{$wsUlid}/sites/{$siteUlid}/media";
}

it('el owner sube un asset y lo lista', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = cmsOwnerContext();
    Sanctum::actingAs($user);
    $url = mediaUrl($ws->ulid, $site->ulid);

    $this->postJson($url, ['file' => UploadedFile::fake()->image('foto.jpg', 800, 600)])
        ->assertCreated()
        ->assertJsonPath('data.status', 'processing')
        ->assertJsonPath('data.mime_type', 'image/jpeg');

    $this->getJson($url)->assertOk()->assertJsonCount(1, 'data');
});

it('procesa las variantes y el show devuelve el asset ready con sus URLs', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = cmsOwnerContext();
    Sanctum::actingAs($user);
    $url = mediaUrl($ws->ulid, $site->ulid);

    // Cola sync en tests: el job de variantes corre durante la subida.
    $id = $this->postJson($url, ['file' => UploadedFile::fake()->image('foto.jpg', 800, 600)])->json('data.id');

    $response = $this->getJson("{$url}/{$id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'ready');

    expect($response->json('data.variants.thumb'))->toBeString();
});

it('rechaza un tipo de archivo no permitido', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = cmsOwnerContext();
    Sanctum::actingAs($user);

    $this->postJson(mediaUrl($ws->ulid, $site->ulid), ['file' => UploadedFile::fake()->create('malware.exe', 10)])
        ->assertStatus(422)
        ->assertJsonValidationErrors('file');
});

it('actualiza alt/title y elimina el asset', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = cmsOwnerContext();
    Sanctum::actingAs($user);
    $url = mediaUrl($ws->ulid, $site->ulid);

    $id = $this->postJson($url, ['file' => UploadedFile::fake()->image('foto.jpg', 400, 300)])->json('data.id');

    $this->patchJson("{$url}/{$id}", ['alt' => 'Un gato', 'title' => 'Michi'])
        ->assertOk()
        ->assertJsonPath('data.alt', 'Un gato')
        ->assertJsonPath('data.title', 'Michi');

    $this->deleteJson("{$url}/{$id}")->assertNoContent();
    $this->getJson("{$url}/{$id}")->assertNotFound();
});

it('el plan free no puede acceder (capability 403)', function () {
    ['user' => $user, 'workspace' => $ws] = registered('free@example.com');
    $site = withinWorkspace($ws, fn () => app(CreateSite::class)->handle(['name' => 'Blog', 'slug' => 'blog']));
    Sanctum::actingAs($user);

    $this->getJson(mediaUrl($ws->ulid, $site->ulid))->assertForbidden();
});

it('editor gestiona; viewer sólo lee (RBAC)', function () {
    ['ws' => $ws, 'site' => $site] = cmsOwnerContext();
    $url = mediaUrl($ws->ulid, $site->ulid);

    Sanctum::actingAs(memberWithRole($ws, 'editor'));
    $this->postJson($url, ['file' => UploadedFile::fake()->image('e.jpg', 300, 300)])->assertCreated();

    Sanctum::actingAs(memberWithRole($ws, 'viewer'));
    $this->getJson($url)->assertOk();
    $this->postJson($url, ['file' => UploadedFile::fake()->image('v.jpg', 300, 300)])->assertForbidden();
});

it('aísla assets entre sites del mismo workspace', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $siteA] = cmsOwnerContext();
    Sanctum::actingAs($user);

    $id = $this->postJson(mediaUrl($ws->ulid, $siteA->ulid), ['file' => UploadedFile::fake()->image('a.jpg', 300, 300)])->json('data.id');

    $siteB = withinWorkspace($ws, fn () => app(CreateSite::class)->handle(['name' => 'Otro', 'slug' => 'otro']));

    $this->getJson(mediaUrl($ws->ulid, $siteB->ulid)."/{$id}")->assertNotFound();
});
