<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Application\Jobs;

use App\Modules\Analytics\Infrastructure\Models\AnalyticsEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Poda del raw de analítica (ADR-021): borra los eventos más antiguos que la retención
 * (`analytics.retention_days`). Los rollups diarios son permanentes; el raw sólo existe para
 * poder recalcular y ver detalle reciente. Job de plataforma (sin global scope). `<= 0` = sin
 * poda (retención infinita).
 */
final class PruneRawEvents implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(): void
    {
        $days = (int) config('sassblog.analytics.retention_days');

        if ($days <= 0) {
            return;
        }

        AnalyticsEvent::withoutGlobalScopes()
            ->where('occurred_at', '<', now()->subDays($days))
            ->delete();
    }
}
