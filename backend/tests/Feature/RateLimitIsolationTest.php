<?php

declare(strict_types=1);

use App\Modules\Domains\Infrastructure\Models\SiteDomain;
use Illuminate\Support\Str;

/**
 * Los rate-limits de endpoints distintos NO comparten contador. Un `throttle:N,M` sin nombre
 * usa como clave sha1(dominio|ip): todos los endpoints de una misma IP sumaban en el MISMO
 * contador. Como el renderer llama a `collect` (y a `resolve`) en CADA render desde una sola
 * IP, eso agotaba el límite de login (y, en prod, el de `resolve` → 404 en todos los dominios
 * propios). Con limiters con nombre, cada endpoint cuenta aparte.
 */
function burstCollect(int $times): void
{
    // ULID de un sitio inexistente: el throttle cuenta el hit, pero no se escribe nada (rápido).
    $ghostSite = Str::upper((string) Str::ulid());
    for ($i = 0; $i < $times; $i++) {
        test()->postJson('/api/v1/public/analytics/collect', ['site' => $ghostSite, 'path' => '/'])->assertNoContent();
    }
}

it('las llamadas de analítica desde una IP no agotan el límite de login', function () {
    registered('lector@example.com');

    burstCollect(15); // > 10/min del login

    $this->postJson('/api/v1/login', ['email' => 'lector@example.com', 'password' => 'Password!123'])->assertOk();
});

it('la ingesta de analítica no agota el resolve de dominios propios', function () {
    [$ws, $site] = builderSite();
    withinWorkspace($ws, fn () => SiteDomain::factory()->active()->create(['site_id' => $site->id, 'hostname' => 'blog.acme.test']));

    burstCollect(130); // > lo que admitía el contador compartido (120/min)

    $this->getJson('/api/v1/public/domains/resolve?host=blog.acme.test')
        ->assertOk()
        ->assertJsonPath('data.site', $site->ulid);
});
