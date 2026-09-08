<?php

declare(strict_types=1);

namespace App\Modules\Builder\Application;

use App\Modules\Builder\Infrastructure\Models\Page;
use App\Modules\Builder\Infrastructure\Models\PageVersion;
use RuntimeException;
use stdClass;

/**
 * Guarda el page schema del draft de una página, in situ (sin historial por
 * guardado en FASE 2). El schema ya viene validado por el Form Request contra el
 * registry (ADR-004); este servicio no revalida.
 *
 * Acepta el schema como objetos (stdClass, desde el JSON crudo) o como array. Al
 * asignarlo, el cast json_encodea; un stdClass preserva los objetos vacíos `{}`
 * que un array asociativo perdería como `[]`.
 */
final class SaveDraft
{
    public function handle(Page $page, mixed $schema): PageVersion
    {
        $draft = $page->draftVersion;

        if ($draft === null) {
            throw new RuntimeException('La página no tiene un draft para editar.');
        }

        $draft->schema = $schema;

        $version = $this->schemaVersion($schema);
        if ($version !== null) {
            $draft->schema_version = $version;
        }

        $draft->save();

        return $draft;
    }

    private function schemaVersion(mixed $schema): ?int
    {
        $value = $schema instanceof stdClass
            ? ($schema->schema_version ?? null)
            : (is_array($schema) ? ($schema['schema_version'] ?? null) : null);

        return is_int($value) ? $value : null;
    }
}
