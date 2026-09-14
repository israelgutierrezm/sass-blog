<?php

declare(strict_types=1);

namespace App\Modules\Content\Console;

use App\Modules\Content\Application\Jobs\PublishScheduledEntries;
use Illuminate\Console\Command;

/**
 * Publica las entradas programadas vencidas (ADR-023). Para el scheduler (cada minuto) y para
 * ejecutar a mano o en tests. Síncrono: hace el trabajo en el propio proceso.
 */
final class PublishScheduledEntriesCommand extends Command
{
    protected $signature = 'content:publish-scheduled';

    protected $description = 'Publica las entradas programadas cuya fecha ya venció.';

    public function handle(): int
    {
        PublishScheduledEntries::dispatchSync();

        $this->info('Entradas programadas vencidas publicadas.');

        return self::SUCCESS;
    }
}
