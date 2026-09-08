<?php

declare(strict_types=1);

namespace App\Modules\Content\Events;

/**
 * Se emite al publicar una entry, dentro del contexto del workspace y de la
 * transacción de PublishEntry. Canal de efectos cruzados de Content: hoy lo consume
 * la auditoría; costura para el futuro módulo Publishing (invalidar caché / rebuild).
 */
final class EntryPublished
{
    public function __construct(
        public readonly int $workspaceId,
        public readonly int $siteId,
        public readonly int $collectionId,
        public readonly int $entryId,
        public readonly ?int $publishedBy,
    ) {}
}
