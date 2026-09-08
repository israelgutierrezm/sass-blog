<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Rendering;

use App\Modules\Content\Infrastructure\Models\Collection;
use App\Modules\Content\Infrastructure\Models\Entry;

/**
 * Proyección de una Entry a tarjeta para el CollectionGrid (ADR-013). Forma estable
 * consumida por el componente; el backend calcula `path`. `excerpt`/`image` salen de
 * claves de campo por convención (excerpt/featured_image) cuando existen.
 * Requiere `author` y `categories` ya cargados (batch anti-N+1).
 */
final class EntryCard
{
    /**
     * @return array<string, mixed>
     */
    public static function fromEntry(Entry $entry, Collection $collection): array
    {
        $category = $entry->categories->first();

        return [
            'id' => $entry->ulid,
            'title' => $entry->title,
            'path' => self::path($collection, $entry),
            'excerpt' => is_string($entry->data['excerpt'] ?? null) ? $entry->data['excerpt'] : null,
            'image' => is_string($entry->data['featured_image'] ?? null) ? $entry->data['featured_image'] : null,
            'date' => $entry->published_at?->toIso8601String(),
            'author' => $entry->author === null ? null : [
                'name' => $entry->author->name,
                'slug' => $entry->author->slug,
            ],
            'category' => $category === null ? null : [
                'name' => $category->name,
                'slug' => $category->slug,
            ],
        ];
    }

    private static function path(Collection $collection, Entry $entry): ?string
    {
        $prefix = $collection->route_prefix;

        return is_string($prefix) && $prefix !== '' ? '/'.$prefix.'/'.$entry->slug : null;
    }
}
