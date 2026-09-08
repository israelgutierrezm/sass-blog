<?php

declare(strict_types=1);

namespace App\Modules\Builder\Application\Schema;

use RuntimeException;

/**
 * Carga los JSON Schema del registry generados por packages/site-schema
 * (ADR-004). El backend NUNCA reescribe reglas: valida contra estos artefactos.
 * Se regeneran con `pnpm build:schema` y viven commiteados en resources/site-schema.
 */
final class SchemaRepository
{
    /** @var array<string, object> */
    private array $cache = [];

    public function schema(string $profile): object
    {
        return $this->cache[$profile] ??= $this->load($profile);
    }

    private function load(string $profile): object
    {
        $file = $profile === 'publish'
            ? 'registry.v1.publish.schema.json'
            : 'registry.v1.draft.schema.json';

        $path = resource_path("site-schema/{$file}");

        if (! is_file($path)) {
            throw new RuntimeException(
                "Falta el JSON Schema del registry: {$file}. Regenera con `pnpm build:schema`."
            );
        }

        $decoded = json_decode((string) file_get_contents($path));

        if (! is_object($decoded)) {
            throw new RuntimeException("JSON Schema inválido: {$file}");
        }

        return $decoded;
    }
}
