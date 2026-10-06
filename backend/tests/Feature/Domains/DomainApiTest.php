<?php

declare(strict_types=1);

use App\Modules\Domains\Application\DnsResolver;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeDnsResolver;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
    // DNS fake: por defecto todo apunta a nuestro ingress; los TXT los «publica» cada test.
    $this->dns = new FakeDnsResolver(default: [config('sassblog.domains.ingress_cname')]);
    app()->instance(DnsResolver::class, $this->dns);
});

function domainsUrl(string $wsUlid, string $siteUlid): string
{
    return "/api/v1/workspaces/{$wsUlid}/sites/{$siteUlid}/domains";
}

/** Publica en el DNS fake el TXT de propiedad que el admin le muestra al dueño del dominio. */
function publishOwnershipTxt(FakeDnsResolver $dns, TestResponse $created): void
{
    $dns->publishTxt($created->json('data.verification.txt_name'), $created->json('data.verification.txt_value'));
}

it('conectar crea la reclamación PENDIENTE con los registros DNS a publicar (no verifica aún)', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = cmsOwnerContext();
    Sanctum::actingAs($user);

    $res = $this->postJson(domainsUrl($ws->ulid, $site->ulid), ['hostname' => 'blog.acme.com'])
        ->assertCreated()
        ->assertJsonPath('data.hostname', 'blog.acme.com')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.failure_reason', null)
        ->assertJsonPath('data.is_primary', true) // primer dominio del sitio
        ->assertJsonPath('data.verification.txt_name', '_sassblog-verify.blog.acme.com')
        ->assertJsonPath('data.verification.cname', config('sassblog.domains.ingress_cname'));

    expect($res->json('data.verification.txt_value'))->toStartWith('sassblog-verify=');
});

it('con el TXT de propiedad y el apuntado, «Verificar» lo deja activo', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = cmsOwnerContext();
    Sanctum::actingAs($user);
    $url = domainsUrl($ws->ulid, $site->ulid);

    $created = $this->postJson($url, ['hostname' => 'blog.acme.com'])->assertCreated();
    publishOwnershipTxt($this->dns, $created);

    // Cola sync: el job corre inline.
    $this->postJson("{$url}/{$created->json('data.id')}/recheck")
        ->assertOk()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.failure_reason', null);
});

it('apuntar a nosotros NO basta: sin el TXT queda failed/ownership (takeover de dominio colgante)', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = cmsOwnerContext();
    Sanctum::actingAs($user);
    $url = domainsUrl($ws->ulid, $site->ulid);

    $id = $this->postJson($url, ['hostname' => 'blog.acme.com'])->json('data.id');

    $this->postJson("{$url}/{$id}/recheck")
        ->assertOk()
        ->assertJsonPath('data.status', 'failed')
        ->assertJsonPath('data.failure_reason', 'ownership');
});

it('con el TXT pero sin apuntar al ingress queda failed/routing', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = cmsOwnerContext();
    Sanctum::actingAs($user);
    $this->dns = new FakeDnsResolver(default: ['9.9.9.9']); // no apunta a nosotros
    app()->instance(DnsResolver::class, $this->dns);
    $url = domainsUrl($ws->ulid, $site->ulid);

    $created = $this->postJson($url, ['hostname' => 'blog.acme.com']);
    publishOwnershipTxt($this->dns, $created);

    $this->postJson("{$url}/{$created->json('data.id')}/recheck")
        ->assertJsonPath('data.status', 'failed')
        ->assertJsonPath('data.failure_reason', 'routing');
});

it('un TXT viejo que quedó publicado no le sirve a otra cuenta (token por reclamación)', function () {
    ['user' => $ownerA, 'ws' => $wsA, 'site' => $siteA] = cmsOwnerContext('a@example.com');
    ['user' => $ownerB, 'ws' => $wsB, 'site' => $siteB] = cmsOwnerContext('b@example.com');
    $urlA = domainsUrl($wsA->ulid, $siteA->ulid);
    $urlB = domainsUrl($wsB->ulid, $siteB->ulid);

    // A verifica el dominio y luego lo desconecta, dejando su CNAME y su TXT publicados.
    Sanctum::actingAs($ownerA);
    $created = $this->postJson($urlA, ['hostname' => 'blog.acme.com']);
    publishOwnershipTxt($this->dns, $created);
    $this->postJson("{$urlA}/{$created->json('data.id')}/recheck")->assertJsonPath('data.status', 'active');
    $this->deleteJson("{$urlA}/{$created->json('data.id')}")->assertNoContent();

    // B lo reclama: apunta a nosotros y hay un TXT… pero con el token de A, no el suyo.
    Sanctum::actingAs($ownerB);
    $idB = $this->postJson($urlB, ['hostname' => 'blog.acme.com'])->assertCreated()->json('data.id');
    $this->postJson("{$urlB}/{$idB}/recheck")
        ->assertJsonPath('data.status', 'failed')
        ->assertJsonPath('data.failure_reason', 'ownership');

    $this->getJson('/api/v1/public/domains/resolve?host=blog.acme.com')->assertNotFound();
});

it('una reclamación ajena sin verificar NO bloquea al dueño real', function () {
    ['user' => $attacker, 'ws' => $wsX, 'site' => $siteX] = cmsOwnerContext('x@example.com');
    ['user' => $owner, 'ws' => $ws, 'site' => $site] = cmsOwnerContext('owner@example.com');
    $urlX = domainsUrl($wsX->ulid, $siteX->ulid);
    $url = domainsUrl($ws->ulid, $site->ulid);

    // Se adelanta a reclamarlo…
    Sanctum::actingAs($attacker);
    $idX = $this->postJson($urlX, ['hostname' => 'blog.acme.com'])->assertCreated()->json('data.id');

    // …pero el dueño real puede reclamarlo igual y, con su TXT, activarlo.
    Sanctum::actingAs($owner);
    $created = $this->postJson($url, ['hostname' => 'blog.acme.com'])->assertCreated();
    publishOwnershipTxt($this->dns, $created);
    $this->postJson("{$url}/{$created->json('data.id')}/recheck")->assertJsonPath('data.status', 'active');

    // La reclamación ajena nunca pasa (su token no está en el DNS del dueño).
    Sanctum::actingAs($attacker);
    $this->postJson("{$urlX}/{$idX}/recheck")->assertJsonPath('data.failure_reason', 'ownership');

    $this->getJson('/api/v1/public/domains/resolve?host=blog.acme.com')->assertOk()->assertJsonPath('data.site', $site->ulid);
});

it('si el hostname ya está activo en otro sitio, no se lo quita: failed/taken', function () {
    ['user' => $ownerA, 'ws' => $wsA, 'site' => $siteA] = cmsOwnerContext('a@example.com');
    ['user' => $ownerB, 'ws' => $wsB, 'site' => $siteB] = cmsOwnerContext('b@example.com');
    $urlA = domainsUrl($wsA->ulid, $siteA->ulid);
    $urlB = domainsUrl($wsB->ulid, $siteB->ulid);

    Sanctum::actingAs($ownerA);
    $createdA = $this->postJson($urlA, ['hostname' => 'blog.acme.com']);
    publishOwnershipTxt($this->dns, $createdA);
    $this->postJson("{$urlA}/{$createdA->json('data.id')}/recheck")->assertJsonPath('data.status', 'active');

    // Migración entre sitios: B ya publicó su TXT, pero A aún lo tiene activo.
    Sanctum::actingAs($ownerB);
    $createdB = $this->postJson($urlB, ['hostname' => 'blog.acme.com']);
    publishOwnershipTxt($this->dns, $createdB);
    $this->postJson("{$urlB}/{$createdB->json('data.id')}/recheck")
        ->assertJsonPath('data.status', 'failed')
        ->assertJsonPath('data.failure_reason', 'taken');

    $this->getJson('/api/v1/public/domains/resolve?host=blog.acme.com')->assertJsonPath('data.site', $siteA->ulid);

    // A lo desconecta → B ya puede activarlo.
    Sanctum::actingAs($ownerA);
    $this->deleteJson("{$urlA}/{$createdA->json('data.id')}")->assertNoContent();
    Sanctum::actingAs($ownerB);
    $this->postJson("{$urlB}/{$createdB->json('data.id')}/recheck")->assertJsonPath('data.status', 'active');
    $this->getJson('/api/v1/public/domains/resolve?host=blog.acme.com')->assertJsonPath('data.site', $siteB->ulid);
});

it('un dominio activo no se re-verifica desde el panel (422): no se deja de servir', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = cmsOwnerContext();
    Sanctum::actingAs($user);
    $url = domainsUrl($ws->ulid, $site->ulid);

    $created = $this->postJson($url, ['hostname' => 'blog.acme.com']);
    publishOwnershipTxt($this->dns, $created);
    $this->postJson("{$url}/{$created->json('data.id')}/recheck")->assertJsonPath('data.status', 'active');

    $this->postJson("{$url}/{$created->json('data.id')}/recheck")->assertStatus(422);
    $this->getJson('/api/v1/public/domains/resolve?host=blog.acme.com')->assertOk();
});

it('normaliza el hostname (esquema, path, mayúsculas, punto final)', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = cmsOwnerContext();
    Sanctum::actingAs($user);

    $this->postJson(domainsUrl($ws->ulid, $site->ulid), ['hostname' => 'HTTPS://Blog.ACME.com/ruta'])
        ->assertCreated()
        ->assertJsonPath('data.hostname', 'blog.acme.com');
});

it('rechaza un hostname inválido y uno ya conectado a ESTE sitio', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = cmsOwnerContext();
    Sanctum::actingAs($user);
    $url = domainsUrl($ws->ulid, $site->ulid);

    $this->postJson($url, ['hostname' => 'no-es-dominio'])->assertStatus(422)->assertJsonValidationErrors('hostname');

    $this->postJson($url, ['hostname' => 'dup.acme.com'])->assertCreated();
    $this->postJson($url, ['hostname' => 'dup.acme.com'])->assertStatus(422)->assertJsonValidationErrors('hostname');
});

it('verifica una reclamación y cambia el primario', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = cmsOwnerContext();
    Sanctum::actingAs($user);
    $url = domainsUrl($ws->ulid, $site->ulid);

    $first = $this->postJson($url, ['hostname' => 'uno.acme.com']);
    $second = $this->postJson($url, ['hostname' => 'dos.acme.com'])->assertJsonPath('data.is_primary', false)->json('data.id');

    $this->postJson("{$url}/{$second}/primary")->assertOk()->assertJsonPath('data.is_primary', true);

    publishOwnershipTxt($this->dns, $first);
    $this->postJson("{$url}/{$first->json('data.id')}/recheck")->assertOk()->assertJsonPath('data.status', 'active');
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
