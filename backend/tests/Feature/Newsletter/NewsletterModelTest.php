<?php

declare(strict_types=1);

use App\Modules\Newsletter\Infrastructure\Models\Campaign;
use App\Modules\Newsletter\Infrastructure\Models\CampaignSend;
use App\Modules\Newsletter\Infrastructure\Models\Subscriber;
use App\Modules\Sites\Infrastructure\Models\Site;
use Illuminate\Database\QueryException;

it('crea un suscriptor con workspace/site, ulid y estado pending', function () {
    [$ws, $site] = builderSite();

    withinWorkspace($ws, function () use ($site) {
        $s = Subscriber::factory()->create(['site_id' => $site->id, 'email' => 'a@example.com']);

        expect($s->workspace_id)->not->toBeNull()
            ->and($s->site_id)->toBe($site->id)
            ->and($s->ulid)->not->toBeNull()
            ->and($s->status)->toBe(Subscriber::STATUS_PENDING)
            ->and($s->isConfirmed())->toBeFalse();
    });
});

it('el email es único por sitio', function () {
    [$ws, $site] = builderSite();

    withinWorkspace($ws, function () use ($site) {
        Subscriber::factory()->create(['site_id' => $site->id, 'email' => 'dup@example.com']);

        expect(fn () => Subscriber::factory()->create(['site_id' => $site->id, 'email' => 'dup@example.com']))
            ->toThrow(QueryException::class);
    });
});

it('permite el mismo email en sitios distintos', function () {
    [$ws, $siteA] = builderSite();
    $siteB = withinWorkspace($ws, fn () => Site::factory()->create());

    withinWorkspace($ws, function () use ($siteA, $siteB) {
        Subscriber::factory()->create(['site_id' => $siteA->id, 'email' => 'same@example.com']);
        $b = Subscriber::factory()->create(['site_id' => $siteB->id, 'email' => 'same@example.com']);

        expect($b->exists)->toBeTrue();
    });
});

it('aísla los suscriptores entre workspaces (global scope)', function () {
    [$wsA, $siteA] = builderSite();
    [$wsB] = builderSite();

    withinWorkspace($wsA, fn () => Subscriber::factory()->create(['site_id' => $siteA->id]));

    withinWorkspace($wsB, fn () => expect(Subscriber::count())->toBe(0));
    withinWorkspace($wsA, fn () => expect(Subscriber::count())->toBe(1));
});

it('la campaña arranca en draft con contadores a cero', function () {
    [$ws, $site] = builderSite();

    withinWorkspace($ws, function () use ($site) {
        $c = Campaign::factory()->create(['site_id' => $site->id]);

        expect($c->status)->toBe(Campaign::STATUS_DRAFT)
            ->and($c->sent_count)->toBe(0)
            ->and($c->isDraft())->toBeTrue()
            ->and($c->ulid)->not->toBeNull();
    });
});

it('el envío por-destinatario es único (idempotencia del envío)', function () {
    [$ws, $site] = builderSite();

    withinWorkspace($ws, function () use ($site) {
        $c = Campaign::factory()->create(['site_id' => $site->id]);
        $s = Subscriber::factory()->create(['site_id' => $site->id]);

        CampaignSend::factory()->create(['campaign_id' => $c->id, 'subscriber_id' => $s->id]);

        expect(fn () => CampaignSend::factory()->create(['campaign_id' => $c->id, 'subscriber_id' => $s->id]))
            ->toThrow(QueryException::class);
    });
});
