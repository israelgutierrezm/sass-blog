<?php

declare(strict_types=1);

use App\Modules\Newsletter\Infrastructure\Models\Campaign;
use App\Modules\Newsletter\Infrastructure\Models\CampaignSend;
use App\Modules\Newsletter\Infrastructure\Models\Subscriber;
use App\Modules\Newsletter\Mail\CampaignMail;
use App\Modules\Sites\Infrastructure\Models\Site;
use App\Modules\Tenancy\Infrastructure\Models\Workspace;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    Mail::fake();
});

function newsletterUrl(Workspace $ws, Site $site): string
{
    return "/api/v1/workspaces/{$ws->ulid}/sites/{$site->ulid}/newsletter";
}

it('lista los suscriptores del sitio', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = ownerWithSite();
    withinWorkspace($ws, fn () => Subscriber::factory()->confirmed()->count(2)->create(['site_id' => $site->id]));
    Sanctum::actingAs($user);

    $this->getJson(newsletterUrl($ws, $site).'/subscribers')->assertOk()->assertJsonCount(2, 'data');
});

it('crea una campaña en borrador', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = ownerWithSite();
    Sanctum::actingAs($user);

    $this->postJson(newsletterUrl($ws, $site).'/campaigns', ['subject' => 'Hola', 'body' => '<p>Cuerpo</p>'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.subject', 'Hola');
});

it('el owner Pro envía una campaña (cola sync → sent)', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = cmsOwnerContext();
    $campaign = withinWorkspace($ws, function () use ($site) {
        Subscriber::factory()->confirmed()->count(2)->create(['site_id' => $site->id]);

        return Campaign::factory()->create(['site_id' => $site->id]);
    });
    Sanctum::actingAs($user);

    $this->postJson(newsletterUrl($ws, $site)."/campaigns/{$campaign->ulid}/send")
        ->assertOk()
        ->assertJsonPath('data.status', 'sent');

    Mail::assertSent(CampaignMail::class, 2);
});

it('el plan básico no puede enviar (403) y no manda correos', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = ownerWithSite();
    $campaign = withinWorkspace($ws, fn () => Campaign::factory()->create(['site_id' => $site->id]));
    Sanctum::actingAs($user);

    $this->postJson(newsletterUrl($ws, $site)."/campaigns/{$campaign->ulid}/send")->assertForbidden();
    Mail::assertNothingSent();
    withinWorkspace($ws, fn () => expect(Campaign::find($campaign->id)->status)->toBe(Campaign::STATUS_DRAFT));
});

it('no re-envía una campaña ya enviada (422)', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = cmsOwnerContext();
    $campaign = withinWorkspace($ws, fn () => Campaign::factory()->sent()->create(['site_id' => $site->id]));
    Sanctum::actingAs($user);

    $this->postJson(newsletterUrl($ws, $site)."/campaigns/{$campaign->ulid}/send")->assertStatus(422);
});

it('doble clic: una campaña que ya se está enviando no se vuelve a encolar (422, sin correos)', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = cmsOwnerContext();
    $campaign = withinWorkspace($ws, function () use ($site) {
        Subscriber::factory()->confirmed()->create(['site_id' => $site->id]);

        return Campaign::factory()->create(['site_id' => $site->id, 'status' => Campaign::STATUS_SENDING]);
    });
    Sanctum::actingAs($user);

    $this->postJson(newsletterUrl($ws, $site)."/campaigns/{$campaign->ulid}/send")->assertStatus(422);
    Mail::assertNothingSent();
});

it('una campaña interrumpida (failed) se reanuda sin repetir a quien ya recibió', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = cmsOwnerContext();
    $campaign = withinWorkspace($ws, function () use ($site) {
        $subs = Subscriber::factory()->confirmed()->count(3)->create(['site_id' => $site->id]);
        $campaign = Campaign::factory()->create(['site_id' => $site->id, 'status' => Campaign::STATUS_FAILED]);
        CampaignSend::factory()->create(['campaign_id' => $campaign->id, 'subscriber_id' => $subs[0]->id]);

        return $campaign;
    });
    Sanctum::actingAs($user);

    $this->postJson(newsletterUrl($ws, $site)."/campaigns/{$campaign->ulid}/send")
        ->assertOk()
        ->assertJsonPath('data.status', 'sent')
        ->assertJsonPath('data.sent_count', 3);

    Mail::assertSent(CampaignMail::class, 2); // sólo los 2 que faltaban
});

it('stats cuenta los suscriptores por estado en BD (no sobre la página de 50 del listado)', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = ownerWithSite();
    withinWorkspace($ws, function () use ($site) {
        Subscriber::factory()->confirmed()->count(55)->create(['site_id' => $site->id]);
        Subscriber::factory()->count(2)->create(['site_id' => $site->id]);             // pending
        Subscriber::factory()->unsubscribed()->create(['site_id' => $site->id]);
    });
    Sanctum::actingAs($user);

    $this->getJson(newsletterUrl($ws, $site).'/subscribers/stats')
        ->assertOk()
        ->assertExactJson(['data' => ['pending' => 2, 'confirmed' => 55, 'unsubscribed' => 1, 'total' => 58]]);
});

it('un viewer no puede gestionar la newsletter (403)', function () {
    ['ws' => $ws, 'site' => $site] = ownerWithSite();
    $viewer = memberWithRole($ws, 'viewer');
    Sanctum::actingAs($viewer);

    $this->getJson(newsletterUrl($ws, $site).'/subscribers')->assertForbidden();
    $this->getJson(newsletterUrl($ws, $site).'/subscribers/stats')->assertForbidden();
});

it('aísla la newsletter entre workspaces (sitio ajeno → 404)', function () {
    ['user' => $userA, 'ws' => $wsA] = ownerWithSite('a@example.com');
    ['ws' => $wsB, 'site' => $siteB] = ownerWithSite('b@example.com');
    withinWorkspace($wsB, fn () => Subscriber::factory()->confirmed()->count(3)->create(['site_id' => $siteB->id]));
    Sanctum::actingAs($userA);

    $this->getJson(newsletterUrl($wsA, $siteB).'/subscribers')->assertNotFound();
    $this->getJson(newsletterUrl($wsA, $siteB).'/subscribers/stats')->assertNotFound();
});
