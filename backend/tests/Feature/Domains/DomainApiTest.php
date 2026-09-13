<?php

declare(strict_types=1);

use App\Modules\Domains\Application\DnsResolver;
use Database\Seeders\DatabaseSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeDnsResolver;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
    // DNS fake que, por defecto, apunta a nuestro ingress → la verificación queda en verde.
    app()->instance(DnsResolver::class, new FakeDnsResolver(default: [config('sassblog.domains.ingress_cname')]));
});

function domainsUrl(string $wsUlid, string $siteUlid): string
{
    return "/api/v1/workspaces/{$wsUlid}/sites/{$siteUlid}/domains";
}

it('el owner Pro conecta un dominio y, si el DNS apunta, queda activo', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = cmsOwnerContext();
    Sanctum::actingAs($user);
    $url = domainsUrl($ws->ulid, $site->ulid);

    $this->postJson($url, ['hostname' => 'blog.acme.com'])
        ->assertCreated()
        ->assertJsonPath('data.hostname', 'blog.acme.com')
        ->assertJsonPath('data.is_primary', true); // primer dominio del sitio

    // Cola sync + DNS fake apunta → el job lo dejó activo.
    $this->getJson($url)->assertOk()->assertJsonPath('data.0.status', 'active');
});

it('si el DNS NO apunta, la verificación falla', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = cmsOwnerContext();
    Sanctum::actingAs($user);
    app()->instance(DnsResolver::class, new FakeDnsResolver(default: ['9.9.9.9'])); // no apunta a nosotros
    $url = domainsUrl($ws->ulid, $site->ulid);

    $this->postJson($url, ['hostname' => 'blog.acme.com'])->assertCreated();
    $this->getJson($url)->assertOk()->assertJsonPath('data.0.status', 'failed');
});

it('normaliza el hostname (esquema, path, mayúsculas, punto final)', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = cmsOwnerContext();
    Sanctum::actingAs($user);

    $this->postJson(domainsUrl($ws->ulid, $site->ulid), ['hostname' => 'HTTPS://Blog.ACME.com/ruta'])
        ->assertCreated()
        ->assertJsonPath('data.hostname', 'blog.acme.com');
});

it('rechaza un hostname inválido y uno duplicado', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = cmsOwnerContext();
    Sanctum::actingAs($user);
    $url = domainsUrl($ws->ulid, $site->ulid);

    $this->postJson($url, ['hostname' => 'no-es-dominio'])->assertStatus(422)->assertJsonValidationErrors('hostname');

    $this->postJson($url, ['hostname' => 'dup.acme.com'])->assertCreated();
    $this->postJson($url, ['hostname' => 'dup.acme.com'])->assertStatus(422)->assertJsonValidationErrors('hostname');
});

it('re-verifica y cambia el primario', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = cmsOwnerContext();
    Sanctum::actingAs($user);
    $url = domainsUrl($ws->ulid, $site->ulid);

    $first = $this->postJson($url, ['hostname' => 'uno.acme.com'])->json('data.id');
    $second = $this->postJson($url, ['hostname' => 'dos.acme.com'])->assertJsonPath('data.is_primary', false)->json('data.id');

    $this->postJson("{$url}/{$second}/primary")->assertOk()->assertJsonPath('data.is_primary', true);
    $this->postJson("{$url}/{$first}/recheck")->assertOk()->assertJsonPath('data.status', 'active');
});

it('el plan free no puede conectar dominios (capability 403)', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = ownerWithSite();
    Sanctum::actingAs($user);

    $this->postJson(domainsUrl($ws->ulid, $site->ulid), ['hostname' => 'x.acme.com'])->assertForbidden();
});

it('editor y viewer no pueden gestionar dominios (RBAC domain.manage)', function () {
    ['ws' => $ws, 'site' => $site] = cmsOwnerContext();
    $url = domainsUrl($ws->ulid, $site->ulid);

    foreach (['editor', 'viewer'] as $role) {
        Sanctum::actingAs(memberWithRole($ws, $role));
        $this->getJson($url)->assertForbidden();
        $this->postJson($url, ['hostname' => 'x.acme.com'])->assertForbidden();
    }
});

it('exige autenticación', function () {
    ['ws' => $ws, 'site' => $site] = ownerWithSite();

    $this->getJson(domainsUrl($ws->ulid, $site->ulid))->assertUnauthorized();
});
