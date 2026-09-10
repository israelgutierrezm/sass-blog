<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Rendering;

use App\Modules\Content\Infrastructure\Models\Entry;
use App\Modules\Shared\Domain\Rendering\SitemapUrl;
use App\Modules\Shared\Domain\Rendering\SitemapUrlSource;

/**
 * Fuente de sitemap de Content (ADR-018): las entries PUBLICADAS de colecciones
 * enrutables (`/{route_prefix}/{slug}`, ADR-011). Excluye borradores, futuras y
 * colecciones sin prefijo/plantilla. Corre dentro del WorkspaceContext del render.
 */
final class EntrySitemapSource implements SitemapUrlSource
{
    public function urlsForSite(int $siteId): iterable
    {
        return Entry::query()
            ->where('site_id', $siteId)
            ->published()
            ->whereHas('collection', fn ($q) => $q->whereNotNull('route_prefix')->whereNotNull('template_page_id'))
            ->with('collection:id,route_prefix')
            ->orderBy('slug')
            ->get()
            ->map(fn (Entry $entry): SitemapUrl => new SitemapUrl(
                '/'.$entry->collection->route_prefix.'/'.$entry->slug,
                $entry->updated_at,
            ))
            ->all();
    }
}
