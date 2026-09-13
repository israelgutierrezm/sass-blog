<?php

declare(strict_types=1);

use App\Modules\Domains\Infrastructure\Models\SiteDomain;

it('resolve devuelve el sitio para un dominio activo', function () {
    [$ws, $site] = builderSite();
    withinWorkspace($ws, fn () => SiteDomain::factory()->active()->create(['site_id' => $site->id, 'hostname' => 'blog.acme.com']));

    $this->getJson('/api/v1/public/domains/resolve?host=blog.acme.com')
        ->assertOk()
        ->assertJsonPath('data.site', $site->ulid);
});

it('resolve normaliza el host (puerto y mayúsculas)', function () {
    [$ws, $site] = builderSite();
    withinWorkspace($ws, fn () => SiteDomain::factory()->active()->create(['site_id' => $site->id, 'hostname' => 'blog.acme.com']));

    $this->getJson('/api/v1/public/domains/resolve?host=Blog.ACME.com:443')
        ->assertOk()
        ->assertJsonPath('data.site', $site->ulid);
});

it('resolve da 404 para un dominio no activo o inexistente', function () {
    [$ws, $site] = builderSite();
    withinWorkspace($ws, fn () => SiteDomain::factory()->create(['site_id' => $site->id, 'hostname' => 'pending.acme.com'])); // pending

    $this->getJson('/api/v1/public/domains/resolve?host=pending.acme.com')->assertNotFound();
    $this->getJson('/api/v1/public/domains/resolve?host=no-existe.com')->assertNotFound();
});

it('tls-check da 200 SÓLO para dominios activos (gate de emisión de certs)', function () {
    [$ws, $site] = builderSite();
    withinWorkspace($ws, function () use ($site) {
        SiteDomain::factory()->active()->create(['site_id' => $site->id, 'hostname' => 'ok.acme.com']);
        SiteDomain::factory()->create(['site_id' => $site->id, 'hostname' => 'no.acme.com']); // pending
    });

    $this->get('/api/v1/public/domains/tls-check?domain=ok.acme.com')->assertOk();
    $this->get('/api/v1/public/domains/tls-check?domain=no.acme.com')->assertNotFound();
    $this->get('/api/v1/public/domains/tls-check?domain=nada.com')->assertNotFound();
});

it('las superficies públicas de dominios no exigen auth', function () {
    // Sin token: 404 (no encontrado), nunca 401.
    $this->getJson('/api/v1/public/domains/resolve?host=x.com')->assertNotFound();
    $this->get('/api/v1/public/domains/tls-check?domain=x.com')->assertNotFound();
});
