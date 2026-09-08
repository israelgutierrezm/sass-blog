<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Tenancy\Infrastructure\Models\Workspace;
use App\Modules\Tenancy\Infrastructure\Models\WorkspaceMember;
use Database\Seeders\DatabaseSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

it('el dueño crea y lista sites en su workspace', function () {
    ['user' => $user, 'workspace' => $ws] = registered();
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/workspaces/{$ws->ulid}/sites", [
        'name' => 'Marketing',
        'slug' => 'marketing',
    ])->assertCreated()
        ->assertJsonPath('data.slug', 'marketing')
        ->assertJsonPath('data.status', 'draft');

    $this->getJson("/api/v1/workspaces/{$ws->ulid}/sites")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('aísla los sites entre workspaces sobre HTTP', function () {
    ['user' => $a, 'workspace' => $wa] = registered('a@example.com');
    ['user' => $b, 'workspace' => $wb] = registered('b@example.com');

    Sanctum::actingAs($a);
    $this->postJson("/api/v1/workspaces/{$wa->ulid}/sites", ['name' => 'A', 'slug' => 'a-site'])
        ->assertCreated();

    // B no ve nada en su propio workspace...
    Sanctum::actingAs($b);
    $this->getJson("/api/v1/workspaces/{$wb->ulid}/sites")
        ->assertOk()
        ->assertJsonCount(0, 'data');

    // ...y no puede siquiera entrar al workspace de A (no es miembro).
    $this->getJson("/api/v1/workspaces/{$wa->ulid}/sites")
        ->assertForbidden();
});

it('un site de otro workspace no se resuelve por su ULID (404, no fuga)', function () {
    ['user' => $a, 'workspace' => $wa] = registered('a@example.com');
    ['user' => $b, 'workspace' => $wb] = registered('b@example.com');

    Sanctum::actingAs($a);
    $siteUlid = $this->postJson("/api/v1/workspaces/{$wa->ulid}/sites", ['name' => 'A', 'slug' => 'a-site'])
        ->json('data.id');

    // B intenta leer el site de A colando su ULID bajo el workspace de B.
    Sanctum::actingAs($b);
    $this->getJson("/api/v1/workspaces/{$wb->ulid}/sites/{$siteUlid}")
        ->assertNotFound();
});

it('un miembro viewer puede listar pero no crear (autorización)', function () {
    ['workspace' => $ws] = registered('owner@example.com');

    $viewer = User::factory()->create();
    withinWorkspace($ws, function () use ($ws, $viewer): void {
        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id' => $viewer->id,
            'role' => 'viewer',
            'joined_at' => now(),
        ]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($ws->id);
        $viewer->assignRole('viewer');
    });

    Sanctum::actingAs($viewer);

    $this->getJson("/api/v1/workspaces/{$ws->ulid}/sites")->assertOk();
    $this->postJson("/api/v1/workspaces/{$ws->ulid}/sites", ['name' => 'X', 'slug' => 'x'])
        ->assertForbidden();
});

it('lista los workspaces del usuario autenticado', function () {
    ['user' => $user] = registered('multi@example.com');

    Sanctum::actingAs($user);
    $this->getJson('/api/v1/workspaces')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.role', 'owner');
});
