<?php

declare(strict_types=1);

use App\Modules\Analytics\Application\Jobs\PruneRawEvents;
use App\Modules\Analytics\Application\Jobs\RollUpDailyStats;
use App\Modules\Analytics\Infrastructure\Models\AnalyticsDailyStat;
use App\Modules\Analytics\Infrastructure\Models\AnalyticsEvent;
use App\Modules\Sites\Infrastructure\Models\Site;
use App\Modules\Tenancy\Infrastructure\Models\Workspace;

/**
 * Escenario base: sitio S, día D. Visitante A ve '/' y '/precios'; visitante B ve '/'; un bot
 * ve '/'; y hay un evento de A en el día anterior. Se comparte entre varios tests.
 *
 * @return array{0: Workspace, 1: Site, 2: string}
 */
function seedPageviews(): array
{
    [$ws, $site] = builderSite();
    $day = '2026-09-13';
    $at = $day.' 10:00:00';

    withinWorkspace($ws, function () use ($site, $at) {
        $mk = fn (string $path, string $hash, bool $bot = false, ?string $when = null) => AnalyticsEvent::factory()->create([
            'site_id' => $site->id, 'path' => $path, 'visitor_hash' => $hash, 'occurred_at' => $when ?? $at, 'is_bot' => $bot,
        ]);

        $mk('/', 'hashA');
        $mk('/precios', 'hashA');
        $mk('/', 'hashB');
        $mk('/', 'hashBOT', true);              // bot: excluido
        $mk('/', 'hashA', false, '2026-09-12 10:00:00'); // otro día: excluido
    });

    return [$ws, $site, $day];
}

it('agrega por ruta, excluye bots y sólo el día objetivo', function () {
    [$ws, $site, $day] = seedPageviews();

    RollUpDailyStats::dispatchSync($day);

    withinWorkspace($ws, function () use ($site, $day) {
        $root = AnalyticsDailyStat::forSite($site->id)->where('stat_date', $day)->where('path', '/')->sole();
        $precios = AnalyticsDailyStat::forSite($site->id)->where('stat_date', $day)->where('path', '/precios')->sole();

        expect($root->views)->toBe(2)->and($root->visitors)->toBe(2)     // A + B (el bot no cuenta)
            ->and($precios->views)->toBe(1)->and($precios->visitors)->toBe(1);
    });
});

it('el total del sitio no sobrecuenta visitantes únicos (no es la suma por ruta)', function () {
    [$ws, $site, $day] = seedPageviews();

    RollUpDailyStats::dispatchSync($day);

    withinWorkspace($ws, function () use ($site, $day) {
        $total = AnalyticsDailyStat::forSite($site->id)->where('stat_date', $day)->where('path', AnalyticsDailyStat::SITE_TOTAL)->sole();

        // views = 3 (suma), pero visitors = 2 (A y B distintos en TODO el sitio), no 3.
        expect($total->views)->toBe(3)->and($total->visitors)->toBe(2);
    });
});

it('es idempotente: re-agregar la misma fecha no duplica ni cambia los totales', function () {
    [$ws, $site, $day] = seedPageviews();

    RollUpDailyStats::dispatchSync($day);
    RollUpDailyStats::dispatchSync($day);

    withinWorkspace($ws, function () use ($site, $day) {
        // 3 filas: '/', '/precios' y el total del sitio.
        expect(AnalyticsDailyStat::forSite($site->id)->where('stat_date', $day)->count())->toBe(3)
            ->and(AnalyticsDailyStat::forSite($site->id)->where('stat_date', $day)->where('path', '/')->sole()->views)->toBe(2);
    });
});

it('poda los eventos más antiguos que la retención y conserva los recientes', function () {
    [$ws, $site] = builderSite();
    config(['sassblog.analytics.retention_days' => 90]);

    withinWorkspace($ws, function () use ($site) {
        AnalyticsEvent::factory()->create(['site_id' => $site->id, 'occurred_at' => now()->subDays(120)]);
        AnalyticsEvent::factory()->create(['site_id' => $site->id, 'occurred_at' => now()->subDays(10)]);
    });

    (new PruneRawEvents)->handle();

    expect(AnalyticsEvent::withoutGlobalScopes()->count())->toBe(1);
});

it('el comando analytics:rollup agrega la fecha dada', function () {
    [$ws, $site, $day] = seedPageviews();

    $this->artisan('analytics:rollup', ['date' => $day])->assertSuccessful();

    withinWorkspace($ws, fn () => expect(
        AnalyticsDailyStat::forSite($site->id)->where('stat_date', $day)->where('path', AnalyticsDailyStat::SITE_TOTAL)->exists()
    )->toBeTrue());
});
