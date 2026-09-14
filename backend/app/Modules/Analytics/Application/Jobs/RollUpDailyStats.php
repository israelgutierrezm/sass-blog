<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Application\Jobs;

use App\Modules\Analytics\Infrastructure\Models\AnalyticsDailyStat;
use App\Modules\Analytics\Infrastructure\Models\AnalyticsEvent;
use App\Modules\Shared\Domain\Tenancy\WorkspaceContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Agrega los eventos crudos de un día en el rollup diario (ADR-021). Es un job de PLATAFORMA
 * (cross-tenant): LEE sin el global scope de workspace, pero ESCRIBE dentro del
 * WorkspaceContext de cada tenant (el trait rellena `workspace_id`, que no es fillable).
 * Sólo cuentan los eventos humanos (is_bot = false). Idempotente: UPSERT sobre (site, día,
 * ruta), así re-ejecutar la misma fecha recalcula sin duplicar.
 *
 * Escribe DOS granularidades: una fila por ruta real y una fila `path = SITE_TOTAL` con las
 * visitas y visitantes únicos de TODO el sitio (los únicos de sitio no son la suma por ruta).
 */
final class RollUpDailyStats implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly string $date) {}

    public function handle(WorkspaceContext $context): void
    {
        $day = Carbon::parse($this->date);
        $date = $day->toDateString();
        $start = $day->copy()->startOfDay();
        $end = $day->copy()->endOfDay();

        $base = fn () => AnalyticsEvent::withoutGlobalScopes()
            ->where('is_bot', false)
            ->whereBetween('occurred_at', [$start, $end]);

        $perPath = $base()
            ->groupBy('workspace_id', 'site_id', 'path')
            ->get(['workspace_id', 'site_id', 'path', DB::raw('COUNT(*) as views'), DB::raw('COUNT(DISTINCT visitor_hash) as visitors')])
            ->map(fn ($r) => $this->row((int) $r->workspace_id, (int) $r->site_id, (string) $r->path, (int) $r->views, (int) $r->visitors));

        $perSite = $base()
            ->groupBy('workspace_id', 'site_id')
            ->get(['workspace_id', 'site_id', DB::raw('COUNT(*) as views'), DB::raw('COUNT(DISTINCT visitor_hash) as visitors')])
            ->map(fn ($r) => $this->row((int) $r->workspace_id, (int) $r->site_id, AnalyticsDailyStat::SITE_TOTAL, (int) $r->views, (int) $r->visitors));

        // Escribe agrupando por workspace: un runFor por tenant (el trait fija workspace_id).
        $perPath->concat($perSite)->groupBy('workspace_id')->each(function ($rows, $workspaceId) use ($context, $date): void {
            $context->runFor((int) $workspaceId, function () use ($rows, $date): void {
                foreach ($rows as $row) {
                    AnalyticsDailyStat::updateOrCreate(
                        ['site_id' => $row['site_id'], 'stat_date' => $date, 'path' => $row['path']],
                        ['views' => $row['views'], 'visitors' => $row['visitors']],
                    );
                }
            });
        });
    }

    /**
     * @return array{workspace_id: int, site_id: int, path: string, views: int, visitors: int}
     */
    private function row(int $workspaceId, int $siteId, string $path, int $views, int $visitors): array
    {
        return ['workspace_id' => $workspaceId, 'site_id' => $siteId, 'path' => $path, 'views' => $views, 'visitors' => $visitors];
    }
}
