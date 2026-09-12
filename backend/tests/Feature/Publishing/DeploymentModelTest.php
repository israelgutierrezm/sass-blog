<?php

declare(strict_types=1);

use App\Modules\Publishing\Infrastructure\Models\Deployment;
use App\Modules\Sites\Infrastructure\Models\Site;

it('crea un deployment con workspace/site, ulid y defaults', function () {
    [$ws, $site] = builderSite();

    withinWorkspace($ws, function () use ($site) {
        $deployment = Deployment::factory()->create(['site_id' => $site->id]);

        expect($deployment->workspace_id)->not->toBeNull()
            ->and($deployment->site_id)->toBe($site->id)
            ->and($deployment->ulid)->not->toBeNull()
            ->and($deployment->target)->toBe(Deployment::TARGET_STATIC)
            ->and($deployment->status)->toBe(Deployment::STATUS_PENDING)
            ->and($deployment->isTerminal())->toBeFalse();
    });
});

it('reconoce los estados terminales', function () {
    [$ws, $site] = builderSite();

    withinWorkspace($ws, function () use ($site) {
        expect(Deployment::factory()->success()->create(['site_id' => $site->id])->isTerminal())->toBeTrue()
            ->and(Deployment::factory()->create(['site_id' => $site->id, 'status' => Deployment::STATUS_FAILED])->isTerminal())->toBeTrue()
            ->and(Deployment::factory()->create(['site_id' => $site->id, 'status' => Deployment::STATUS_BUILDING])->isTerminal())->toBeFalse();
    });
});

it('aísla los deployments entre workspaces (global scope)', function () {
    [$wsA, $siteA] = builderSite();
    [$wsB] = builderSite();

    withinWorkspace($wsA, fn () => Deployment::factory()->create(['site_id' => $siteA->id]));

    withinWorkspace($wsB, fn () => expect(Deployment::count())->toBe(0));
    withinWorkspace($wsA, fn () => expect(Deployment::count())->toBe(1));
});

it('aísla los deployments por sitio', function () {
    [$ws, $siteA] = builderSite();
    $siteB = withinWorkspace($ws, fn () => Site::factory()->create());

    withinWorkspace($ws, function () use ($siteA, $siteB) {
        Deployment::factory()->create(['site_id' => $siteA->id]);
        Deployment::factory()->create(['site_id' => $siteB->id]);

        expect(Deployment::forSite($siteA->id)->count())->toBe(1)
            ->and(Deployment::forSite($siteB->id)->count())->toBe(1);
    });
});
