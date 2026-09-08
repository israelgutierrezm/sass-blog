<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Tenancy\Infrastructure\Models\WorkspaceMember;
use Database\Seeders\DatabaseSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

it('registra al usuario y aprovisiona workspace, rol owner y suscripción free', function () {
    $response = $this->postJson('/api/v1/register', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'Password!123',
        'password_confirmation' => 'Password!123',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.email', 'ada@example.com')
        ->assertJsonStructure(['data' => ['id', 'name', 'email', 'workspaces'], 'token']);

    $user = User::where('email', 'ada@example.com')->firstOrFail();
    $workspace = $user->ownedWorkspaces()->firstOrFail();

    expect($user->ownedWorkspaces()->count())->toBe(1)
        ->and($workspace->personal)->toBeTrue();

    withinWorkspace($workspace, function () use ($user, $workspace): void {
        expect(WorkspaceMember::where('user_id', $user->id)->exists())->toBeTrue()
            ->and($workspace->subscription()->exists())->toBeTrue()
            ->and($workspace->subscription->plan->key)->toBe('free');
    });

    app(PermissionRegistrar::class)->setPermissionsTeamId($workspace->id);
    expect($user->fresh()->hasRole('owner'))->toBeTrue();
});

it('rechaza registro con correo duplicado', function () {
    User::factory()->create(['email' => 'dup@example.com']);

    $this->postJson('/api/v1/register', [
        'name' => 'X',
        'email' => 'dup@example.com',
        'password' => 'Password!123',
        'password_confirmation' => 'Password!123',
    ])->assertStatus(422)->assertJsonValidationErrorFor('email');
});

it('inicia sesión y el token da acceso a /me', function () {
    User::factory()->create(['email' => 'leo@example.com', 'password' => 'Password!123']);

    $token = $this->postJson('/api/v1/login', [
        'email' => 'leo@example.com',
        'password' => 'Password!123',
    ])->assertOk()->json('token');

    $this->withToken($token)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'leo@example.com');
});

it('rechaza credenciales inválidas', function () {
    User::factory()->create(['email' => 'leo@example.com', 'password' => 'Password!123']);

    $this->postJson('/api/v1/login', [
        'email' => 'leo@example.com',
        'password' => 'wrong-password',
    ])->assertStatus(422);
});

it('sin token, /me responde 401', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized();
});
