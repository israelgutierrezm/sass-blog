<?php

declare(strict_types=1);

use App\Modules\Domains\Infrastructure\Models\SiteDomain;
use App\Modules\Sites\Infrastructure\Models\Site;
use Illuminate\Database\QueryException;

it('crea un dominio con workspace/site, ulid y defaults', function () {
    [$ws, $site] = builderSite();

    withinWorkspace($ws, function () use ($site) {
        $domain = SiteDomain::factory()->create(['site_id' => $site->id, 'hostname' => 'blog.acme.com']);

        expect($domain->workspace_id)->not->toBeNull()
            ->and($domain->site_id)->toBe($site->id)
            ->and($domain->ulid)->not->toBeNull()
            ->and($domain->status)->toBe(SiteDomain::STATUS_PENDING)
            ->and($domain->ssl_status)->toBe(SiteDomain::SSL_NONE)
            ->and($domain->isActive())->toBeFalse();
    });
});

it('el hostname es único en toda la plataforma (aun entre sitios distintos)', function () {
    [$ws, $siteA] = builderSite();
    $siteB = withinWorkspace($ws, fn () => Site::factory()->create());

    withinWorkspace($ws, function () use ($siteA, $siteB) {
        SiteDomain::factory()->create(['site_id' => $siteA->id, 'hostname' => 'dup.acme.com']);

        expect(fn () => SiteDomain::factory()->create(['site_id' => $siteB->id, 'hostname' => 'dup.acme.com']))
            ->toThrow(QueryException::class);
    });
});

it('aísla los dominios entre workspaces (global scope)', function () {
    [$wsA, $siteA] = builderSite();
    [$wsB] = builderSite();

    withinWorkspace($wsA, fn () => SiteDomain::factory()->create(['site_id' => $siteA->id]));

    withinWorkspace($wsB, fn () => expect(SiteDomain::count())->toBe(0));
    withinWorkspace($wsA, fn () => expect(SiteDomain::count())->toBe(1));
});

it('reconoce el estado activo', function () {
    [$ws, $site] = builderSite();

    withinWorkspace($ws, function () use ($site) {
        expect(SiteDomain::factory()->active()->create(['site_id' => $site->id])->isActive())->toBeTrue();
    });
});
