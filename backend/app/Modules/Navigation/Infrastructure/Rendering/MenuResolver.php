<?php

declare(strict_types=1);

namespace App\Modules\Navigation\Infrastructure\Rendering;

use App\Modules\Builder\Infrastructure\Models\Page;
use App\Modules\Content\Infrastructure\Models\Collection;
use App\Modules\Content\Infrastructure\Models\Entry;
use App\Modules\Navigation\Infrastructure\Models\Menu;
use App\Modules\Navigation\Infrastructure\Models\MenuItem;
use App\Modules\Shared\Domain\Rendering\SectionDataResolver;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Resuelve una sección `navigation` al árbol del menú (ADR-017): props.menu = handle.
 * Los paths se resuelven EN VIVO desde la referencia (respeta slug history): page→su
 * path; entry→`/{route_prefix}/{slug}`; collection→`/{route_prefix}`; url→tal cual;
 * home→`/`. Las referencias rotas (destino inexistente o sin ruta) se OMITEN con su
 * subárbol. Batch anti-N+1: los destinos se cargan por tipo en lote. Implementa el
 * contrato de kernel; Builder/render no dependen de Navigation.
 */
final class MenuResolver implements SectionDataResolver
{
    public function supports(string $type): bool
    {
        return $type === 'navigation';
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
        $handle = is_string($props['menu'] ?? null) ? $props['menu'] : null;

        if ($handle === null || $siteId === 0) {
            return ['items' => [], 'total' => 0];
        }

        $menu = Menu::query()->where('site_id', $siteId)->where('handle', $handle)->first();
        if ($menu === null) {
            return ['items' => [], 'total' => 0];
        }

        $items = MenuItem::query()->where('menu_id', $menu->id)->orderBy('position')->get();
        $urls = $this->resolveUrls($items, $siteId);
        $tree = $this->buildTree($items, null, $urls);

        return ['items' => $tree, 'total' => count($tree)];
    }

    /**
     * Resuelve el path de cada ítem (id => url|null) cargando los destinos en LOTE.
     *
     * @param  SupportCollection<int, MenuItem>  $items
     * @return array<int, string|null>
     */
    private function resolveUrls(SupportCollection $items, int $siteId): array
    {
        $targetsBy = fn (string $type): array => $items
            ->where('link_type', $type)
            ->pluck('target_ulid')
            ->filter()
            ->unique()
            ->all();

        $pageTargets = $targetsBy(MenuItem::LINK_PAGE);
        $entryTargets = $targetsBy(MenuItem::LINK_ENTRY);
        $collectionTargets = $targetsBy(MenuItem::LINK_COLLECTION);

        // page → path (una sola consulta; se omite si no hay destinos de página)
        $pagePaths = $pageTargets === [] ? collect() : Page::query()
            ->where('site_id', $siteId)
            ->whereIn('ulid', $pageTargets)
            ->pluck('path', 'ulid');

        // entry → /{route_prefix}/{slug} (sólo si su colección es enrutable)
        $entryPaths = [];
        if ($entryTargets !== []) {
            Entry::query()
                ->where('site_id', $siteId)
                ->whereIn('ulid', $entryTargets)
                ->with('collection:id,route_prefix')
                ->get()
                ->each(function (Entry $entry) use (&$entryPaths): void {
                    $prefix = $entry->collection?->route_prefix;
                    if (is_string($prefix) && $prefix !== '') {
                        $entryPaths[$entry->ulid] = '/'.$prefix.'/'.$entry->slug;
                    }
                });
        }

        // collection → /{route_prefix} (índice)
        $collectionPaths = [];
        if ($collectionTargets !== []) {
            Collection::query()
                ->where('site_id', $siteId)
                ->whereIn('ulid', $collectionTargets)
                ->get()
                ->each(function (Collection $collection) use (&$collectionPaths): void {
                    if (is_string($collection->route_prefix) && $collection->route_prefix !== '') {
                        $collectionPaths[$collection->ulid] = '/'.$collection->route_prefix;
                    }
                });
        }

        $urls = [];
        foreach ($items as $item) {
            $urls[$item->id] = match ($item->link_type) {
                MenuItem::LINK_HOME => '/',
                MenuItem::LINK_URL => $item->url,
                MenuItem::LINK_PAGE => $pagePaths[$item->target_ulid] ?? null,
                MenuItem::LINK_ENTRY => $entryPaths[$item->target_ulid] ?? null,
                MenuItem::LINK_COLLECTION => $collectionPaths[$item->target_ulid] ?? null,
                default => null,
            };
        }

        return $urls;
    }

    /**
     * Árbol jerárquico; omite los ítems sin url resuelta (y su subárbol).
     *
     * @param  SupportCollection<int, MenuItem>  $items
     * @param  array<int, string|null>  $urls
     * @return list<array<string, mixed>>
     */
    private function buildTree(SupportCollection $items, ?int $parentId, array $urls): array
    {
        return $items
            ->where('parent_id', $parentId)
            ->sortBy('position')
            ->map(function (MenuItem $item) use ($items, $urls): ?array {
                $url = $urls[$item->id] ?? null;
                if (! is_string($url) || $url === '') {
                    return null; // referencia rota: se omite el ítem y su subárbol
                }

                return [
                    'label' => $item->label,
                    'url' => $url,
                    'children' => $this->buildTree($items, $item->id, $urls),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
