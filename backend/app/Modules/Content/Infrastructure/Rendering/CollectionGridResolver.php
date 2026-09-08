<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Rendering;

use App\Modules\Content\Application\Rendering\EntryCard;
use App\Modules\Content\Infrastructure\Models\Collection;
use App\Modules\Content\Infrastructure\Models\Entry;
use App\Modules\Shared\Domain\Rendering\SectionDataResolver;

/**
 * Resuelve una sección `collection-grid` a tarjetas de entries PUBLICADAS (ADR-013).
 * Honra la query (collection por handle, category por slug, order de lista blanca,
 * limit). Batch anti-N+1: author y categories se cargan en lote (with). Corre dentro
 * de WorkspaceContext (lo fija el render); las consultas quedan scopeadas por tenant.
 */
final class CollectionGridResolver implements SectionDataResolver
{
    public function supports(string $type): bool
    {
        return $type === 'collection-grid';
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

        if ($handle === null || $siteId === 0) {
            return ['items' => [], 'total' => 0];
        }

        $collection = Collection::query()
            ->where('site_id', $siteId)
            ->where('handle', $handle)
            ->first();
        if ($collection === null) {
            return ['items' => [], 'total' => 0];
        }

        $query = Entry::query()
            ->where('collection_id', $collection->id)
            ->published();

        $categorySlug = is_string($props['category'] ?? null) ? $props['category'] : null;
        if ($categorySlug !== null && $categorySlug !== '') {
            $query->whereHas('categories', fn ($q) => $q->where('slug', $categorySlug));
        }

        $total = (clone $query)->count();

        match (is_string($props['order'] ?? null) ? $props['order'] : 'recent') {
            'oldest' => $query->orderBy('published_at')->orderBy('id'),
            'title' => $query->orderBy('title')->orderBy('id'),
            default => $query->orderByDesc('published_at')->orderByDesc('id'),
        };

        $limit = is_int($props['limit'] ?? null) ? max(1, min(48, $props['limit'])) : 6;

        $entries = $query->with(['author', 'categories'])->limit($limit)->get();

        return [
            'items' => $entries->map(fn (Entry $entry) => EntryCard::fromEntry($entry, $collection))->all(),
            'total' => $total,
        ];
    }
}
