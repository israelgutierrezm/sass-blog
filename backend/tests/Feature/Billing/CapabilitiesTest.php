<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Billing\Infrastructure\Models\Capability as CapabilityModel;
use App\Modules\Billing\Infrastructure\Models\Plan;
use App\Modules\Billing\Infrastructure\Models\Subscription;
use App\Modules\Shared\Domain\Capabilities\Capabilities;
use App\Modules\Shared\Domain\Capabilities\Capability;
use App\Modules\Shared\Domain\Capabilities\Exceptions\CapabilityDeniedException;
use App\Modules\Tenancy\Infrastructure\Models\Workspace;

function proPlanWith(Capability $capability, ?int $limit = null): Plan
{
    $plan = Plan::create(['key' => 'pro', 'name' => 'Pro']);
    $cap = CapabilityModel::create(['key' => $capability->value, 'name' => $capability->label()]);
    $plan->capabilities()->attach($cap->id, ['limit' => $limit]);

    return $plan;
}

it('resuelve las capabilities desde el plan del workspace', function () {
    $w = Workspace::factory()->create(['owner_id' => User::factory()->create()->id]);
    $plan = proPlanWith(Capability::CmsCollections, limit: 5);
    withinWorkspace($w, fn () => Subscription::create(['plan_id' => $plan->id, 'status' => 'active']));

    actingForWorkspace($w);
    $caps = app(Capabilities::class);

    expect($caps->allows(Capability::CmsCollections))->toBeTrue()
        ->and($caps->limit(Capability::CmsCollections))->toBe(5)
        ->and($caps->allows(Capability::PublisherPaywall))->toBeFalse();
});

it('authorize lanza cuando el plan no otorga la capability', function () {
    $w = Workspace::factory()->create(['owner_id' => User::factory()->create()->id]);
    $plan = proPlanWith(Capability::CmsCollections);
    withinWorkspace($w, fn () => Subscription::create(['plan_id' => $plan->id, 'status' => 'active']));

    actingForWorkspace($w);
    $caps = app(Capabilities::class);

    $caps->authorize(Capability::CmsCollections); // no lanza
    expect(fn () => $caps->authorize(Capability::PublisherPaywall))
        ->toThrow(CapabilityDeniedException::class);
});

it('un workspace sin suscripción no otorga ninguna capability', function () {
    $w = Workspace::factory()->create(['owner_id' => User::factory()->create()->id]);

    actingForWorkspace($w);
    $caps = app(Capabilities::class);

    expect($caps->allows(Capability::CmsCollections))->toBeFalse();
});

it('las capabilities de un workspace no se filtran a otro', function () {
    $w1 = Workspace::factory()->create(['owner_id' => User::factory()->create()->id]);
    $w2 = Workspace::factory()->create(['owner_id' => User::factory()->create()->id]);

    $plan = proPlanWith(Capability::CmsCollections);
    withinWorkspace($w1, fn () => Subscription::create(['plan_id' => $plan->id, 'status' => 'active']));

    actingForWorkspace($w1);
    expect(app(Capabilities::class)->allows(Capability::CmsCollections))->toBeTrue();

    actingForWorkspace($w2);
    expect(app(Capabilities::class)->allows(Capability::CmsCollections))->toBeFalse();
});
