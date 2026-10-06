<?php

declare(strict_types=1);

use App\Modules\Newsletter\Application\Jobs\SendCampaign;
use App\Modules\Newsletter\Infrastructure\Models\Campaign;
use App\Modules\Newsletter\Infrastructure\Models\CampaignSend;
use App\Modules\Newsletter\Infrastructure\Models\Subscriber;
use App\Modules\Newsletter\Mail\CampaignMail;
use Illuminate\Support\Facades\Mail;

beforeEach(fn () => Mail::fake());

it('envía sólo a los confirmados y actualiza estado/contadores', function () {
    [$ws, $site] = builderSite();
    $campaign = withinWorkspace($ws, function () use ($site) {
        Subscriber::factory()->confirmed()->count(3)->create(['site_id' => $site->id]);
        Subscriber::factory()->count(2)->create(['site_id' => $site->id]);            // pending
        Subscriber::factory()->unsubscribed()->create(['site_id' => $site->id]);       // baja

        return Campaign::factory()->create(['site_id' => $site->id]);
    });

    SendCampaign::dispatchSync($campaign->id, $ws->id);

    Mail::assertSent(CampaignMail::class, 3);
    withinWorkspace($ws, function () use ($campaign) {
        $c = Campaign::find($campaign->id);
        expect($c->status)->toBe(Campaign::STATUS_SENT)
            ->and($c->recipients_count)->toBe(3)
            ->and($c->sent_count)->toBe(3)
            ->and(CampaignSend::where('campaign_id', $c->id)->count())->toBe(3);
    });
});

it('es idempotente: re-enviar no duplica correos', function () {
    [$ws, $site] = builderSite();
    $campaign = withinWorkspace($ws, function () use ($site) {
        Subscriber::factory()->confirmed()->count(2)->create(['site_id' => $site->id]);

        return Campaign::factory()->create(['site_id' => $site->id]);
    });

    SendCampaign::dispatchSync($campaign->id, $ws->id);
    SendCampaign::dispatchSync($campaign->id, $ws->id);

    Mail::assertSent(CampaignMail::class, 2); // no 4
    withinWorkspace($ws, fn () => expect(CampaignSend::where('campaign_id', $campaign->id)->count())->toBe(2));
});

it('el correo lleva el link de baja del suscriptor', function () {
    [$ws, $site] = builderSite();
    $token = str_repeat('a', 64);
    $campaign = withinWorkspace($ws, function () use ($site, $token) {
        Subscriber::factory()->confirmed()->create(['site_id' => $site->id, 'unsubscribe_token' => $token]);

        return Campaign::factory()->create(['site_id' => $site->id]);
    });

    SendCampaign::dispatchSync($campaign->id, $ws->id);

    Mail::assertSent(CampaignMail::class, fn (CampaignMail $mail) => str_contains($mail->unsubscribeUrl, $token));
});

it('un destinatario ya reclamado por otro worker (pending) no recibe un segundo correo', function () {
    [$ws, $site] = builderSite();
    [$campaign, $claimed] = withinWorkspace($ws, function () use ($site) {
        $subs = Subscriber::factory()->confirmed()->count(2)->create(['site_id' => $site->id]);
        $campaign = Campaign::factory()->create(['site_id' => $site->id, 'status' => Campaign::STATUS_SENDING]);
        CampaignSend::factory()->create([
            'campaign_id' => $campaign->id,
            'subscriber_id' => $subs[0]->id,
            'status' => CampaignSend::STATUS_PENDING,
            'sent_at' => null,
        ]);

        return [$campaign, $subs[0]];
    });

    SendCampaign::dispatchSync($campaign->id, $ws->id);

    Mail::assertSent(CampaignMail::class, 1);
    Mail::assertNotSent(CampaignMail::class, fn (CampaignMail $mail) => $mail->hasTo($claimed->email));
});

it('si el job muere, failed() deja la campaña en failed con los contadores parciales', function () {
    [$ws, $site] = builderSite();
    $campaign = withinWorkspace($ws, function () use ($site) {
        $subs = Subscriber::factory()->confirmed()->count(2)->create(['site_id' => $site->id]);
        $campaign = Campaign::factory()->create(['site_id' => $site->id, 'status' => Campaign::STATUS_SENDING]);
        CampaignSend::factory()->create(['campaign_id' => $campaign->id, 'subscriber_id' => $subs[0]->id]);

        return $campaign;
    });

    (new SendCampaign($campaign->id, $ws->id))->failed(new RuntimeException('timeout'));

    withinWorkspace($ws, function () use ($campaign) {
        $c = Campaign::find($campaign->id);
        expect($c->status)->toBe(Campaign::STATUS_FAILED)
            ->and($c->sent_count)->toBe(1)
            ->and($c->failed_count)->toBe(0);
    });
});
