<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Rendering;

/**
 * Arma el sidecar `resolved` de un payload de render (ADR-013): para cada sección
 * DINÁMICA cuyo tipo soporte el resolver, `{ [sectionId]: {items, total} }`. Vive en
 * el kernel y depende sólo de la ABSTRACCIÓN SectionDataResolver, así que tanto Builder
 * (render estático) como Content (render dinámico) lo usan sin acoplarse entre sí.
 */
final class SectionResolution
{
    /**
     * @param  iterable<mixed>  $sections  Secciones del page schema (stdClass o array).
     * @param  array<string, mixed>  $context  { workspace_id, site_id }.
     * @return array<string, array{items: list<array<string, mixed>>, total: int}>|\stdClass
     */
    public static function forSections(iterable $sections, ?SectionDataResolver $resolver, array $context): array|\stdClass
    {
        if ($resolver === null) {
            return new \stdClass; // {} para el renderer, nunca []
        }

        $resolved = [];
        foreach ($sections as $section) {
            $array = self::toArray($section);
            $type = $array['type'] ?? null;
            $id = $array['id'] ?? null;

            if (is_string($type) && is_string($id) && $resolver->supports($type)) {
                $resolved[$id] = $resolver->resolve($array, $context);
            }
        }

        return $resolved === [] ? new \stdClass : $resolved;
    }

    /**
     * @return array<string, mixed>
     */
    private static function toArray(mixed $section): array
    {
        if (is_array($section)) {
            return $section;
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) json_encode($section), true) ?: [];

        return $decoded;
    }
}
