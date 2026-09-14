<?php

declare(strict_types=1);

use App\Modules\Analytics\Infrastructure\Models\AnalyticsEvent;
use Illuminate\Support\Str;

function collectUrl(): string
{
    return '/api/v1/public/analytics/collect';
}

it('registra un pageview de un sitio existente (204, sin auth)', function () {
    ['ws' => $ws, 'site' => $site] = ownerWithSite();

    $this->postJson(collectUrl(), ['site' => $site->ulid, 'path' => '/precios/'], [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/127.0.0.0 Safari/537.36',
        'X-Visitor-Ip' => '203.0.113.9',
    ])->assertNoContent();

    withinWorkspace($ws, function () use ($site) {
        $e = AnalyticsEvent::forSite($site->id)->sole();
        expect($e->path)->toBe('/precios')          // normalizado (sin barra final)
            ->and($e->is_bot)->toBeFalse()
            ->and($e->visitor_hash)->toHaveLength(64);
    });
});

it('descarta silenciosamente (204) un sitio inexistente', function () {
    ownerWithSite();

    $this->postJson(collectUrl(), ['site' => Str::upper((string) Str::ulid()), 'path' => '/'])
        ->assertNoContent();

    expect(AnalyticsEvent::withoutGlobalScopes()->count())->toBe(0);
});

it('marca is_bot por el User-Agent', function () {
    ['ws' => $ws, 'site' => $site] = ownerWithSite();

    $this->postJson(collectUrl(), ['site' => $site->ulid, 'path' => '/'], ['User-Agent' => 'Googlebot/2.1'])
        ->assertNoContent();

    withinWorkspace($ws, fn () => expect(AnalyticsEvent::forSite($site->id)->sole()->is_bot)->toBeTrue());
});

it('reduce el referrer a su host (sin ruta ni query)', function () {
    ['ws' => $ws, 'site' => $site] = ownerWithSite();

    $this->postJson(collectUrl(), ['site' => $site->ulid, 'path' => '/', 'referrer' => 'https://Google.com/search?q=x'])
        ->assertNoContent();

    withinWorkspace($ws, fn () => expect(AnalyticsEvent::forSite($site->id)->sole()->referrer_host)->toBe('google.com'));
});

it('usa X-Visitor-Ip para distinguir visitantes', function () {
    ['ws' => $ws, 'site' => $site] = ownerWithSite();
    $ua = ['User-Agent' => 'SameUA/1.0 (compatible browser)'];

    $this->postJson(collectUrl(), ['site' => $site->ulid, 'path' => '/'], $ua + ['X-Visitor-Ip' => '203.0.113.1'])->assertNoContent();
    $this->postJson(collectUrl(), ['site' => $site->ulid, 'path' => '/'], $ua + ['X-Visitor-Ip' => '203.0.113.2'])->assertNoContent();

    withinWorkspace($ws, function () use ($site) {
        expect(AnalyticsEvent::forSite($site->id)->get()->pluck('visitor_hash')->unique()->count())->toBe(2);
    });
});

it('exige el path (422)', function () {
    ['site' => $site] = ownerWithSite();

    $this->postJson(collectUrl(), ['site' => $site->ulid])->assertStatus(422);
});
