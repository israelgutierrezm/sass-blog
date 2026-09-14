<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Providers;

use App\Modules\Analytics\Infrastructure\Models\AnalyticsDailyStat;
use App\Modules\Analytics\Policies\AnalyticsPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Registro del módulo Analytics (ADR-021). Autoriza la lectura del rollup por la Policy.
 * VisitorHasher/BotDetector no necesitan binding (el contenedor los resuelve). En sub-slices
 * posteriores: ingesta pública `collect`, job de rollup + scheduler, y API del dashboard.
 */
final class AnalyticsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(AnalyticsDailyStat::class, AnalyticsPolicy::class);
    }
}
