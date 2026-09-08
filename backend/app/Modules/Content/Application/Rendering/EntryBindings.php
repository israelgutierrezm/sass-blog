<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Rendering;

use App\Modules\Content\Infrastructure\Models\Entry;

/**
 * Construye el allow-set colección-consciente de bindings para una entry (ADR-012):
 * un mapa CERRADO de ruta permitida → valor. Sólo columnas universales seguras, el
 * autor, y los campos `data` cuyo tipo es BINDEABLE (escalares; nunca media, relación,
 * multiselect ni json). Cualquier ruta fuera de este mapa se resuelve a null.
 *
 * Requiere que la entry tenga cargadas las relaciones `author` y `collection.fields`.
 */
final class EntryBindings
{
    /**
     * @return array<string, mixed>
     */
    public static function map(Entry $entry): array
    {
        $map = [
            'entry.title' => $entry->title,
            'entry.slug' => $entry->slug,
            'entry.status' => $entry->status,
            'entry.path' => self::path($entry),
            'entry.published_at' => $entry->published_at?->toIso8601String(),
            'entry.author.name' => $entry->author?->name,
            'entry.author.slug' => $entry->author?->slug,
        ];

        foreach ($entry->collection->fields as $field) {
            if ($field->type->isBindable()) {
                $map['entry.data.'.$field->key] = $entry->data[$field->key] ?? null;
            }
        }

        return $map;
    }

    private static function path(Entry $entry): ?string
    {
        $prefix = $entry->collection->route_prefix;

        return is_string($prefix) && $prefix !== '' ? '/'.$prefix.'/'.$entry->slug : null;
    }
}
