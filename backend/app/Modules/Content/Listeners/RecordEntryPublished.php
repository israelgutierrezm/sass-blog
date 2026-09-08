<?php

declare(strict_types=1);

namespace App\Modules\Content\Listeners;

use App\Modules\Audit\Application\AuditRecorder;
use App\Modules\Content\Events\EntryPublished;

/**
 * Registra en la bitácora la publicación de una entry. Content (domain) depende de
 * Audit (kernel) a través de su servicio sancionado; no escribe la tabla.
 */
final class RecordEntryPublished
{
    public function __construct(private readonly AuditRecorder $recorder) {}

    public function handle(EntryPublished $event): void
    {
        $this->recorder->record('entry.published', [
            'site_id' => $event->siteId,
            'entity_type' => 'entry',
            'entity_id' => (string) $event->entryId,
            'metadata' => ['collection_id' => $event->collectionId],
        ]);
    }
}
