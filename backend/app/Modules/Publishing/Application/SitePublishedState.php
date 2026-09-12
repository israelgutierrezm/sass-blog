<?php

declare(strict_types=1);

namespace App\Modules\Publishing\Application;

use App\Modules\Builder\Infrastructure\Models\Page;
use App\Modules\Content\Infrastructure\Models\Entry;

/**
 * Hash del estado PUBLICADO de un sitio (ADR-019): páginas estándar publicadas (versión +
 * marca de tiempo) + entries publicadas. Da idempotencia al build — el mismo estado
 * produce el mismo hash, así el job puede evitar reconstruir. Corre dentro del
 * WorkspaceContext (lo fija el request/job); las consultas quedan scopeadas por tenant.
 *
 * MVP: redirects/menús también afectan el render pero no entran al hash (deuda declarada).
 */
final class SitePublishedState
{
    public function hash(int $siteId): string
    {
        $pages = Page::query()
            ->where('site_id', $siteId)
            ->where('kind', Page::KIND_STANDARD)
            ->whereNotNull('published_version_id')
            ->orderBy('id')
            ->get(['id', 'published_version_id', 'updated_at'])
            ->map(fn (Page $page): string => "p:{$page->id}:{$page->published_version_id}:{$page->updated_at}");

        $entries = Entry::query()
            ->where('site_id', $siteId)
            ->published()
            ->orderBy('id')
            ->get(['id', 'updated_at'])
            ->map(fn (Entry $entry): string => "e:{$entry->id}:{$entry->updated_at}");

        return hash('sha256', $pages->concat($entries)->implode('|'));
    }
}
