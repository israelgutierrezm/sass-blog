<?php

declare(strict_types=1);

namespace App\Modules\Builder\Events;

/**
 * Se emite al publicar una página, dentro del contexto del workspace y de la
 * transacción de PublishPage. Único canal de efectos cruzados del Builder: la
 * costura para el futuro módulo Publishing (Fase 5, encolar build/deploy). En
 * FASE 2 lo consume el listener de auditoría.
 */
final class PagePublished
{
    public function __construct(
        public readonly int $workspaceId,
        public readonly int $siteId,
        public readonly int $pageId,
        public readonly int $pageVersionId,
        public readonly ?int $publishedBy,
    ) {}
}
