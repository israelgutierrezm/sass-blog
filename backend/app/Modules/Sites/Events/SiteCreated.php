<?php

declare(strict_types=1);

namespace App\Modules\Sites\Events;

/**
 * Se emite al crear un site, ya dentro del contexto de su workspace.
 *
 * Efecto cruzado SOLO por evento: Content siembra el preset de la colección de
 * artículos (colección + campos + autor y categoría por defecto). Sites no escribe
 * en las tablas de Content directamente.
 */
final class SiteCreated
{
    public function __construct(
        public readonly int $workspaceId,
        public readonly int $siteId,
    ) {}
}
