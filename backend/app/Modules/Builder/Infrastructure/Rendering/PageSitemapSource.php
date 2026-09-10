<?php

declare(strict_types=1);

namespace App\Modules\Builder\Infrastructure\Rendering;

use App\Modules\Builder\Infrastructure\Models\Page;
use App\Modules\Shared\Domain\Rendering\SitemapUrl;
use App\Modules\Shared\Domain\Rendering\SitemapUrlSource;

/**
 * Fuente de sitemap del Builder (ADR-018): las páginas estándar PUBLICADAS del sitio.
 * Las plantillas de colección quedan fuera (no son URLs propias: `kind` template, path
 * NULL). `lastmod` = última modificación de la página. Corre dentro del WorkspaceContext.
 */
final class PageSitemapSource implements SitemapUrlSource
{
    public function urlsForSite(int $siteId): iterable
    {
        return Page::query()
            ->where('site_id', $siteId)
            ->where('kind', Page::KIND_STANDARD)
            ->whereNotNull('published_version_id')
            ->orderBy('path')
            ->get(['path', 'updated_at'])
            ->map(fn (Page $page): SitemapUrl => new SitemapUrl((string) $page->path, $page->updated_at))
            ->all();
    }
}
