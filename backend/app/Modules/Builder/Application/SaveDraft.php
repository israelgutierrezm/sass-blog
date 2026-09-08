<?php

declare(strict_types=1);

namespace App\Modules\Builder\Application;

use App\Modules\Builder\Infrastructure\Models\Page;
use App\Modules\Builder\Infrastructure\Models\PageVersion;
use RuntimeException;

/**
 * Guarda el page schema del draft de una página, in situ (sin historial por
 * guardado en FASE 2). El schema ya viene validado por el Form Request contra el
 * registry (ADR-004); este servicio no revalida.
 *
 * @param  array<string, mixed>  $schema
 */
final class SaveDraft
{
    public function handle(Page $page, array $schema): PageVersion
    {
        $draft = $page->draftVersion;

        if ($draft === null) {
            throw new RuntimeException('La página no tiene un draft para editar.');
        }

        $draft->schema = $schema;

        if (isset($schema['schema_version'])) {
            $draft->schema_version = (int) $schema['schema_version'];
        }

        $draft->save();

        return $draft;
    }
}
