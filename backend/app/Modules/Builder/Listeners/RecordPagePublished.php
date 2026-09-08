<?php

declare(strict_types=1);

namespace App\Modules\Builder\Listeners;

use App\Modules\Audit\Application\AuditRecorder;
use App\Modules\Builder\Events\PagePublished;

/**
 * Registra en la bitácora la publicación de una página. Builder (domain) depende
 * de Audit (kernel) a través de su servicio sancionado; no escribe la tabla.
 */
final class RecordPagePublished
{
    public function __construct(private readonly AuditRecorder $recorder) {}

    public function handle(PagePublished $event): void
    {
        $this->recorder->record('page.published', [
            'site_id' => $event->siteId,
            'entity_type' => 'page',
            'entity_id' => (string) $event->pageId,
            'metadata' => ['page_version_id' => $event->pageVersionId],
        ]);
    }
}
