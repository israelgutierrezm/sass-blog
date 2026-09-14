<?php

declare(strict_types=1);

use App\Modules\Analytics\Infrastructure\Models\AnalyticsDailyStat;
use App\Modules\Analytics\Infrastructure\Models\AnalyticsEvent;
use App\Modules\Sites\Infrastructure\Models\Site;
use App\Modules\Tenancy\Infrastructure\Models\Workspace;
use Database\Seeders\DatabaseSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(fn () => $this->seed(DatabaseSeeder::class));

function analyticsUrl(Workspace $ws, Site $site): string
{
    return "/api/v1/workspaces/{$ws->ulid}/sites/{$site->ulid}/analytics";
}

/** Rollups de hoy: total del sitio (10/7) + dos rutas ('/' 6/5, '/precios' 4/3). */
function seedRollups(Workspace $ws, Site $site): void
{
    withinWorkspace($ws, function () use ($site) {
        $today = today()->toDateString();
        AnalyticsDailyStat::factory()->create(['site_id' => $site->id, 'stat_date' => $today, 'path' => AnalyticsDailyStat::SITE_TOTAL, 'views' => 10, 'visitors' => 7]);
        AnalyticsDailyStat::factory()->create(['site_id' => $site->id, 'stat_date' => $today, 'path' => '/', 'views' => 6, 'visitors' => 5]);
        AnalyticsDailyStat::factory()->create(['site_id' => $site->id, 'stat_date' => $today, 'path' => '/precios', 'views' => 4, 'visitors' => 3]);
    });
}

it('devuelve totales, serie y top de páginas', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = ownerWithSite();
    seedRollups($ws, $site);
    Sanctum::actingAs($user);

    $this->getJson(analyticsUrl($ws, $site).'/summary')
        ->assertOk()
        ->assertJsonPath('data.totals.views', 10)
        ->assertJsonPath('data.totals.visitors', 7)
        ->assertJsonPath('data.top_pages.0.path', '/')      // más vistas primero
        ->assertJsonPath('data.top_pages.0.views', 6)
        ->assertJsonCount(1, 'data.series');
});

it('el plan básico no ve referrers y marca advanced=false', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = ownerWithSite();
    seedRollups($ws, $site);
    Sanctum::actingAs($user);

    $this->getJson(analyticsUrl($ws, $site).'/summary')
        ->assertOk()
        ->assertJsonPath('data.advanced', false)
        ->assertJsonPath('data.top_referrers', null);
});

it('el plan Pro ve referrers y advanced=true', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = cmsOwnerContext();
    seedRollups($ws, $site);
    withinWorkspace($ws, fn () => AnalyticsEvent::factory()->count(3)->create([
        'site_id' => $site->id, 'referrer_host' => 'google.com', 'occurred_at' => now(),
    ]));
    Sanctum::actingAs($user);

    $this->getJson(analyticsUrl($ws, $site).'/summary')
        ->assertOk()
        ->assertJsonPath('data.advanced', true)
        ->assertJsonPath('data.top_referrers.0.referrer', 'google.com')
        ->assertJsonPath('data.top_referrers.0.views', 3);
});

it('un viewer no puede ver la analítica (403)', function () {
    ['ws' => $ws, 'site' => $site] = ownerWithSite();
    $viewer = memberWithRole($ws, 'viewer');
    Sanctum::actingAs($viewer);

    $this->getJson(analyticsUrl($ws, $site).'/summary')->assertForbidden();
});

it('aísla la analítica entre workspaces (sitio ajeno → 404)', function () {
    ['user' => $userA, 'ws' => $wsA] = ownerWithSite('a@example.com');
    ['site' => $siteB] = ownerWithSite('b@example.com');
    Sanctum::actingAs($userA);

    $this->getJson(analyticsUrl($wsA, $siteB).'/summary')->assertNotFound();
});

it('export CSV: Pro 200 text/csv; básico 403', function () {
    ['user' => $pro, 'ws' => $wsPro, 'site' => $sitePro] = cmsOwnerContext('pro@example.com');
    seedRollups($wsPro, $sitePro);
    Sanctum::actingAs($pro);

    $res = $this->get(analyticsUrl($wsPro, $sitePro).'/export');
    $res->assertOk();
    expect($res->headers->get('content-type'))->toContain('text/csv');

    ['user' => $free, 'ws' => $wsFree, 'site' => $siteFree] = ownerWithSite('free@example.com');
    Sanctum::actingAs($free);
    $this->get(analyticsUrl($wsFree, $siteFree).'/export')->assertForbidden();
});
