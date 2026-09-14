<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Rendering;

use App\Modules\Content\Application\Rendering\EntryCard;
use App\Modules\Content\Infrastructure\Models\Collection;
use App\Modules\Content\Infrastructure\Models\Entry;
use App\Modules\Shared\Domain\Rendering\SectionDataResolver;
use Illuminate\Support\Str;

/**
 * Resuelve una sección `featured` (Portada, ADR-024) a tarjetas de artículos elegidos A MANO.
 * `items` es una lista ORDENADA de ULIDs; se cargan en UN `whereIn` (anti-N+1) las entradas
 * PUBLICADAS y se reordenan en memoria según `items`, saltando las no-publicadas/borradas.
 * Corre dentro de WorkspaceContext (scopeado por tenant/site).
 */
final class FeaturedResolver implements SectionDataResolver
{
    public function supports(string $type): bool
    {
        return $type === 'featured';
    }

    /**
     * @param  array<string, mixed>  $section
     * @param  array<string, mixed>  $context
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function resolve(array $section, array $context): array
    {
        /** @var array<string, mixed> $props */
        $props = is_array($section['props'] ?? null) ? $section['props'] : [];
        $siteId = (int) ($context['site_id'] ?? 0);
        $handle = is_string($props['collection'] ?? null) ? $props['collection'] : null;

        /** @var list<string> $ulids */
        $ulids = is_array($props['items'] ?? null)
            ? array_values(array_filter($props['items'], 'is_string'))
            : [];

        if ($handle === null || $siteId === 0 || $ulids === []) {
            return ['items' => [], 'total' => 0];
        }

        $collection = Collection::query()
            ->where('site_id', $siteId)
            ->where('handle', $handle)
            ->first();
        if ($collection === null) {
            return ['items' => [], 'total' => 0];
        }

        $upper = array_map(fn (string $u) => Str::upper($u), $ulids);

        $byUlid = Entry::query()
            ->where('collection_id', $collection->id)
            ->published()
            ->whereIn('ulid', $upper)
            ->with(['author', 'categories'])
            ->get()
            ->keyBy('ulid');

        $items = [];
        foreach ($upper as $ulid) {
            $entry = $byUlid->get($ulid);
            if ($entry instanceof Entry) {
                $items[] = EntryCard::fromEntry($entry, $collection);
            }
        }

        return ['items' => $items, 'total' => count($items)];
    }
}
