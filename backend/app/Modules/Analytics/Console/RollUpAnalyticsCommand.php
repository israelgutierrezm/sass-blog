<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Console;

use App\Modules\Analytics\Application\Jobs\RollUpDailyStats;
use Illuminate\Console\Command;

/**
 * Ejecuta el rollup diario de analítica (ADR-021) para una fecha (por defecto, ayer).
 * Para el scheduler diario y para correr/re-correr a mano o en tests. Síncrono: hace el
 * trabajo en el propio proceso, sin depender de un worker.
 */
final class RollUpAnalyticsCommand extends Command
{
    protected $signature = 'analytics:rollup {date? : Fecha Y-m-d a agregar (por defecto, ayer)}';

    protected $description = 'Agrega los eventos crudos de analítica en el rollup diario.';

    public function handle(): int
    {
        $date = $this->argument('date') ?? now()->subDay()->toDateString();

        RollUpDailyStats::dispatchSync($date);

        $this->info("Rollup de analítica completado para {$date}.");

        return self::SUCCESS;
    }
}
