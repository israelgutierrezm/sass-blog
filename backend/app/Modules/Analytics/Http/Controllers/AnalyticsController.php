<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Analytics\Application\MetricsQuery;
use App\Modules\Analytics\Http\Requests\AnalyticsSummaryRequest;
use App\Modules\Analytics\Http\Resources\AnalyticsSummaryResource;
use App\Modules\Analytics\Infrastructure\Models\AnalyticsDailyStat;
use App\Modules\Shared\Domain\Capabilities\Capabilities;
use App\Modules\Shared\Domain\Capabilities\Capability;
use App\Modules\Sites\Infrastructure\Models\Site;
use App\Modules\Tenancy\Infrastructure\Models\Workspace;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * Dashboard de analítica por sitio (ADR-021). Auth + workspace (contexto) + Policy
 * (`analytics.view`). El plan modula: sin `analytics.advanced` el rango se recorta a la
 * ventana básica y no hay referrers; con él (Pro), rango libre + referrers + export CSV
 * (esta última, además, gateada por la ruta).
 */
final class AnalyticsController extends Controller
{
    public function summary(Workspace $workspace, string $site, AnalyticsSummaryRequest $request, MetricsQuery $metrics, Capabilities $capabilities): AnalyticsSummaryResource
    {
        $this->authorize('viewAny', AnalyticsDailyStat::class);
        $siteModel = $this->resolveSite($site);

        $advanced = $capabilities->allows(Capability::AnalyticsAdvanced);
        [$from, $to] = $this->range($request, $advanced);

        return new AnalyticsSummaryResource([
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'advanced' => $advanced,
            ...$metrics->summary($siteModel->id, $from, $to),
            'top_referrers' => $advanced ? $metrics->topReferrers($siteModel->id, $from, $to) : null,
        ]);
    }

    /**
     * Export CSV de la serie diaria (Pro: la ruta lleva `capability:analytics.advanced`).
     */
    public function export(Workspace $workspace, string $site, AnalyticsSummaryRequest $request, MetricsQuery $metrics): Response
    {
        $this->authorize('viewAny', AnalyticsDailyStat::class);
        $siteModel = $this->resolveSite($site);

        [$from, $to] = $this->range($request, true);
        $series = $metrics->summary($siteModel->id, $from, $to)['series'];

        $csv = "date,views,visitors\n";
        foreach ($series as $row) {
            $csv .= "{$row['date']},{$row['views']},{$row['visitors']}\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="analitica.csv"',
        ]);
    }

    private function resolveSite(string $ulid): Site
    {
        $site = Site::findByUlid($ulid);
        abort_if($site === null, 404, 'Sitio no encontrado.');

        return $site;
    }

    /**
     * Rango efectivo. Por defecto, la ventana básica hasta hoy. Sin plan avanzado se recorta a
     * los últimos `basic_range_days` días (ni más antiguo ni futuro).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function range(AnalyticsSummaryRequest $request, bool $advanced): array
    {
        $basicDays = (int) config('sassblog.analytics.basic_range_days');
        $today = Carbon::today();

        $to = $request->date('to')?->startOfDay() ?? $today->copy();
        $from = $request->date('from')?->startOfDay() ?? $to->copy()->subDays($basicDays - 1);

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        if (! $advanced) {
            $floor = $today->copy()->subDays($basicDays - 1);
            $from = $from->lt($floor) ? $floor : $from;
            $to = $to->gt($today) ? $today->copy() : $to;
        }

        return [$from, $to];
    }
}
