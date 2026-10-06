<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Jobs;

use App\Modules\Content\Events\EntryPublished;
use App\Modules\Content\Infrastructure\Models\Entry;
use App\Modules\Shared\Domain\Tenancy\WorkspaceContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Publica las entradas programadas cuya fecha ya venció (ADR-023). Job de PLATAFORMA: LEE
 * cross-tenant (`scheduled` con `published_at <= now`, sin global scope) pero ESCRIBE dentro del
 * WorkspaceContext de cada tenant. Emite `EntryPublished` en el momento REAL de ir en vivo.
 * Idempotente: sólo toca `scheduled` vencidos; re-ejecutar no vuelve a publicar (ya no son
 * `scheduled`).
 */
final class PublishScheduledEntries implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(WorkspaceContext $context): void
    {
        $due = Entry::withoutGlobalScopes()
            ->where('status', Entry::STATUS_SCHEDULED)
            ->where('published_at', '<=', now())
            ->get();

        $due->groupBy('workspace_id')->each(function ($entries, $workspaceId) use ($context): void {
            $context->runFor((int) $workspaceId, function () use ($entries): void {
                foreach ($entries as $entry) {
                    // Reclamo ATÓMICO: sólo la ejecución cuyo UPDATE condicional toca la fila
                    // emite el evento. Si dos barridos se solapan (o la entrada se retiró entre
                    // la lectura y aquí), el segundo ve 0 filas y no publica ni re-emite.
                    $claimed = Entry::query()
                        ->whereKey($entry->id)
                        ->where('status', Entry::STATUS_SCHEDULED)
                        ->update(['status' => Entry::STATUS_PUBLISHED]);

                    if ($claimed === 0) {
                        continue;
                    }

                    event(new EntryPublished(
                        $entry->workspace_id,
                        $entry->site_id,
                        $entry->collection_id,
                        $entry->id,
                        $entry->updated_by,
                    ));
                }
            });
        });
    }
}
