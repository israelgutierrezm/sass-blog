<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Providers;

use App\Modules\Analytics\Application\Jobs\PruneRawEvents;
use App\Modules\Analytics\Console\RollUpAnalyticsCommand;
use App\Modules\Analytics\Infrastructure\Models\AnalyticsDailyStat;
use App\Modules\Analytics\Policies\AnalyticsPolicy;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Registro del módulo Analytics (ADR-021). Autoriza la lectura del rollup por la Policy,
 * registra el comando de rollup y programa los jobs diarios (rollup del día anterior + poda
 * del raw). VisitorHasher/BotDetector no necesitan binding (el contenedor los resuelve).
 */
final class AnalyticsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(AnalyticsDailyStat::class, AnalyticsPolicy::class);

        if ($this->app->runningInConsole()) {
            $this->commands([RollUpAnalyticsCommand::class]);
        }

        // Programación diaria (requiere `schedule:run` por cron, como el worker de colas).
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('analytics:rollup')->dailyAt('00:20');
            $schedule->job(new PruneRawEvents)->dailyAt('03:00');
        });
    }
}
