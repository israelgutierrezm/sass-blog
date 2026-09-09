<?php

declare(strict_types=1);

use Database\Seeders\DatabaseSeeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

it('reprovisiona permisos del catálogo en workspaces existentes', function () {
    ['user' => $user, 'workspace' => $ws] = registered();

    // Simular un workspace "viejo": al rol owner le falta un permiso nuevo del catálogo.
    withinWorkspace($ws, function () {
        Role::findByName('owner', 'web')->revokePermissionTo('entry.publish');
    });
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $before = withinWorkspace($ws, fn () => $user->fresh()->hasPermissionTo('entry.publish'));
    expect($before)->toBeFalse();

    $this->artisan('identity:reprovision-rbac')->assertSuccessful();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $after = withinWorkspace($ws, fn () => $user->fresh()->hasPermissionTo('entry.publish'));
    expect($after)->toBeTrue();
});
