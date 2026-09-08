<?php

declare(strict_types=1);

use App\Modules\Billing\Infrastructure\Models\Plan;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);

    // Ruta de sondeo con el stack real: auth + workspace + capability (ADR-014).
    Route::middleware(['auth:sanctum', 'workspace', 'capability:cms.collections'])
        ->get('/api/v1/_probe/workspaces/{workspace}', fn () => response()->json(['ok' => true]));
});

it('403 cuando el plan del workspace NO incluye cms.collections (free)', function () {
    ['user' => $u, 'workspace' => $ws] = registered();
    Sanctum::actingAs($u);

    $this->getJson("/api/v1/_probe/workspaces/{$ws->ulid}")->assertForbidden();
});

it('200 cuando el plan del workspace incluye cms.collections (pro)', function () {
    ['user' => $u, 'workspace' => $ws] = registered();

    $pro = Plan::where('key', 'pro')->firstOrFail();
    withinWorkspace($ws, fn () => $ws->subscription()->update(['plan_id' => $pro->id]));

    Sanctum::actingAs($u);
    $this->getJson("/api/v1/_probe/workspaces/{$ws->ulid}")
        ->assertOk()
        ->assertJson(['ok' => true]);
});

it('401 sin autenticar', function () {
    ['workspace' => $ws] = registered();
    $this->getJson("/api/v1/_probe/workspaces/{$ws->ulid}")->assertUnauthorized();
});
