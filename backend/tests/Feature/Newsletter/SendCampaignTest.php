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
