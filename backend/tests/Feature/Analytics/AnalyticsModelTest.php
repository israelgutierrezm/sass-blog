<?php

declare(strict_types=1);

use App\Modules\Analytics\Infrastructure\Models\AnalyticsDailyStat;
use App\Modules\Analytics\Infrastructure\Models\AnalyticsEvent;
use App\Modules\Sites\Infrastructure\Models\Site;
use Illuminate\Database\QueryException;

it('crea un evento con workspace/site y defaults', function () {
    [$ws, $site] = builderSite();

    withinWorkspace($ws, function () use ($site) {
        $e = AnalyticsEvent::factory()->create(['site_id' => $site->id, 'path' => '/precios']);

        expect($e->workspace_id)->not->toBeNull()
            ->and($e->site_id)->toBe($site->id)
            ->and($e->path)->toBe('/precios')
            ->and($e->is_bot)->toBeFalse();
    });
});

it('aísla los eventos entre workspaces (global scope)', function () {
    [$wsA, $siteA] = builderSite();
    [$wsB] = builderSite();

    withinWorkspace($wsA, fn () => AnalyticsEvent::factory()->create(['site_id' => $siteA->id]));

    withinWorkspace($wsB, fn () => expect(AnalyticsEvent::count())->toBe(0));
    withinWorkspace($wsA, fn () => expect(AnalyticsEvent::count())->toBe(1));
});

it('acota los eventos por sitio dentro del workspace', function () {
    [$ws, $siteA] = builderSite();
    $siteB = withinWorkspace($ws, fn () => Site::factory()->create());

    withinWorkspace($ws, function () use ($siteA, $siteB) {
        AnalyticsEvent::factory()->count(2)->create(['site_id' => $siteA->id]);
        AnalyticsEvent::factory()->create(['site_id' => $siteB->id]);

        expect(AnalyticsEvent::forSite($siteA->id)->count())->toBe(2)
            ->and(AnalyticsEvent::forSite($siteB->id)->count())->toBe(1);
    });
});

it('el rollup diario es único por sitio/día/ruta', function () {
    [$ws, $site] = builderSite();

    withinWorkspace($ws, function () use ($site) {
        AnalyticsDailyStat::factory()->create(['site_id' => $site->id, 'stat_date' => '2026-09-13', 'path' => '/']);

        expect(fn () => AnalyticsDailyStat::factory()->create(['site_id' => $site->id, 'stat_date' => '2026-09-13', 'path' => '/']))
            ->toThrow(QueryException::class);
    });
});

it('aísla el rollup entre workspaces (global scope)', function () {
    [$wsA, $siteA] = builderSite();
    [$wsB] = builderSite();

    withinWorkspace($wsA, fn () => AnalyticsDailyStat::factory()->create(['site_id' => $siteA->id]));

    withinWorkspace($wsB, fn () => expect(AnalyticsDailyStat::count())->toBe(0));
    withinWorkspace($wsA, fn () => expect(AnalyticsDailyStat::count())->toBe(1));
});
