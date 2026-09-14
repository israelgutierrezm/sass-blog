<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Application;

use App\Modules\Analytics\Infrastructure\Models\AnalyticsDailyStat;
use App\Modules\Analytics\Infrastructure\Models\AnalyticsEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Consultas de lectura del dashboard (ADR-021). Asume el WorkspaceContext fijado (el
 * middleware 'workspace' lo resuelve) y acota siempre por sitio. Views/visitantes salen del
 * rollup permanente; los referrers, del raw (limitados a la ventana de retención).
 *
 * Nota: `totals.visitors` es la SUMA de únicos diarios (un visitante que vuelve otro día
 * cuenta por día) — así lo entienden los dashboards por rollup diario; la serie diaria sí es
 * exacta por día.
 */
final class MetricsQuery
{
    /**
     * @return array{totals: array{views: int, visitors: int}, series: list<array{date: string, views: int, visitors: int}>, top_pages: list<array{path: string, views: int, visitors: int}>}
     */
    public function summary(int $siteId, Carbon $from, Carbon $to, int $topLimit = 10): array
    {
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        $siteTotal = fn () => AnalyticsDailyStat::forSite($siteId)
            ->where('path', AnalyticsDailyStat::SITE_TOTAL)
            ->whereBetween('stat_date', [$fromDate, $toDate]);

        $totals = $siteTotal()->first([
            DB::raw('COALESCE(SUM(views), 0) as views'),
            DB::raw('COALESCE(SUM(visitors), 0) as visitors'),
        ]);

        $series = $siteTotal()
            ->orderBy('stat_date')
            ->get(['stat_date', 'views', 'visitors'])
            ->map(fn ($r) => ['date' => $r->stat_date->toDateString(), 'views' => (int) $r->views, 'visitors' => (int) $r->visitors])
            ->all();

        $topPages = AnalyticsDailyStat::forSite($siteId)
            ->where('path', '!=', AnalyticsDailyStat::SITE_TOTAL)
            ->whereBetween('stat_date', [$fromDate, $toDate])
            ->groupBy('path')
            ->orderByDesc(DB::raw('SUM(views)'))
            ->limit($topLimit)
            ->get(['path', DB::raw('SUM(views) as views'), DB::raw('SUM(visitors) as visitors')])
            ->map(fn ($r) => ['path' => (string) $r->path, 'views' => (int) $r->views, 'visitors' => (int) $r->visitors])
            ->all();

        return [
            'totals' => ['views' => (int) $totals->views, 'visitors' => (int) $totals->visitors],
            'series' => $series,
            'top_pages' => $topPages,
        ];
    }

    /**
     * Top referrers desde el RAW (Pro). Limitado a lo que quede en el raw (retención).
     *
     * @return list<array{referrer: string, views: int}>
     */
    public function topReferrers(int $siteId, Carbon $from, Carbon $to, int $limit = 10): array
    {
        return AnalyticsEvent::forSite($siteId)
            ->where('is_bot', false)
            ->whereNotNull('referrer_host')
            ->whereBetween('occurred_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->groupBy('referrer_host')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->limit($limit)
            ->get(['referrer_host', DB::raw('COUNT(*) as views')])
            ->map(fn ($r) => ['referrer' => (string) $r->referrer_host, 'views' => (int) $r->views])
            ->all();
    }
}
