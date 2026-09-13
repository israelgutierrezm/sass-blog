<?php

declare(strict_types=1);

use App\Modules\Publishing\Application\StaticRenderer;
use App\Modules\Sites\Application\CreateSite;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeStaticRenderer;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
    Storage::fake('local'); // el ZIP del build va a un disco fake
    app()->bind(StaticRenderer::class, FakeStaticRenderer::class); // sin Node en tests
});

it('el owner Pro dispara un deployment y el build (cola sync) lo completa', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = cmsOwnerContext();
    Sanctum::actingAs($user);
    $url = deploymentsUrl($ws->ulid, $site->ulid);

    $id = $this->postJson($url)->assertStatus(202)->json('data.id');

    // Cola sync en tests: el job corre inline → el deployment queda en success.
    $this->getJson("{$url}/{$id}")->assertOk()->assertJsonPath('data.status', 'success');
    $this->getJson($url)->assertOk()->assertJsonCount(1, 'data');
});

it('el plan free no puede exportar (capability 403)', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = ownerWithSite();
    Sanctum::actingAs($user);

    $this->postJson(deploymentsUrl($ws->ulid, $site->ulid))->assertForbidden();
});

it('editor y viewer no pueden exportar (RBAC site.publish)', function () {
    ['ws' => $ws, 'site' => $site] = cmsOwnerContext();
    $url = deploymentsUrl($ws->ulid, $site->ulid);

    foreach (['editor', 'viewer'] as $role) {
        Sanctum::actingAs(memberWithRole($ws, $role));
        $this->getJson($url)->assertForbidden();
        $this->postJson($url)->assertForbidden();
    }
});

it('exige autenticación', function () {
    ['ws' => $ws, 'site' => $site] = ownerWithSite();

    $this->getJson(deploymentsUrl($ws->ulid, $site->ulid))->assertUnauthorized();
});

it('aísla los deployments entre sitios del mismo workspace', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $siteA] = cmsOwnerContext();
    Sanctum::actingAs($user);

    $id = $this->postJson(deploymentsUrl($ws->ulid, $siteA->ulid))->json('data.id');

    $siteB = withinWorkspace($ws, fn () => app(CreateSite::class)->handle(['name' => 'Otro', 'slug' => 'otro']));

    $this->getJson(deploymentsUrl($ws->ulid, $siteB->ulid)."/{$id}")->assertNotFound();
});
