<?php

declare(strict_types=1);

use App\Modules\Domains\Infrastructure\Models\SiteDomain;
use App\Modules\Sites\Infrastructure\Models\Site;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;

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

it('varias reclamaciones del mismo hostname coexisten, pero sólo UNA puede estar activa (BD)', function () {
    [$ws, $siteA] = builderSite();
    $siteB = withinWorkspace($ws, fn () => Site::factory()->create());

    withinWorkspace($ws, function () use ($siteA, $siteB) {
        $a = SiteDomain::factory()->create(['site_id' => $siteA->id, 'hostname' => 'dup.acme.com']);
        $b = SiteDomain::factory()->create(['site_id' => $siteB->id, 'hostname' => 'dup.acme.com']); // coexisten

        $a->update(['status' => SiteDomain::STATUS_ACTIVE]);

        expect(fn () => $b->update(['status' => SiteDomain::STATUS_ACTIVE]))
            ->toThrow(UniqueConstraintViolationException::class);
    });
});

it('un sitio no reclama dos veces el mismo hostname (BD)', function () {
    [$ws, $site] = builderSite();

    withinWorkspace($ws, function () use ($site) {
        SiteDomain::factory()->create(['site_id' => $site->id, 'hostname' => 'dup.acme.com']);

        expect(fn () => SiteDomain::factory()->create(['site_id' => $site->id, 'hostname' => 'dup.acme.com']))
            ->toThrow(QueryException::class);
    });
});

it('active_hostname (columna generada) sólo tiene valor mientras el dominio está activo', function () {
    [$ws, $site] = builderSite();

    withinWorkspace($ws, function () use ($site) {
        $domain = SiteDomain::factory()->create(['site_id' => $site->id, 'hostname' => 'blog.acme.com']);
        expect($domain->fresh()->active_hostname)->toBeNull();

        $domain->update(['status' => SiteDomain::STATUS_ACTIVE]);
        expect($domain->fresh()->active_hostname)->toBe('blog.acme.com');

        $domain->update(['status' => SiteDomain::STATUS_FAILED]);
        expect($domain->fresh()->active_hostname)->toBeNull();
    });
});

it('el TXT de propiedad lleva el token de la reclamación', function () {
    [$ws, $site] = builderSite();

    withinWorkspace($ws, function () use ($site) {
        $domain = SiteDomain::factory()->create(['site_id' => $site->id, 'hostname' => 'blog.acme.com', 'verification_token' => 'abc123']);

        expect($domain->challengeName())->toBe('_sassblog-verify.blog.acme.com')
            ->and($domain->challengeValue())->toBe('sassblog-verify=abc123');
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
