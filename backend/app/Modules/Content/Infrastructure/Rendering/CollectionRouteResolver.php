<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Rendering;

use App\Modules\Builder\Infrastructure\Models\Page;
use App\Modules\Content\Application\Rendering\BindingResolver;
use App\Modules\Content\Application\Rendering\EntryBindings;
use App\Modules\Content\Infrastructure\Models\Collection;
use App\Modules\Content\Infrastructure\Models\Entry;
use App\Modules\Shared\Domain\Rendering\DynamicRouteResolver;
use App\Modules\Sites\Infrastructure\Models\Site;

/**
 * Resuelve una ruta dinámica de detalle de colección (ADR-011): `/{route_prefix}/{slug}`.
 * Encuentra la colección por prefijo (con plantilla publicada) y la entry PUBLICADA
 * por slug, resuelve los bindings de la plantilla contra la entry y devuelve un payload
 * con la MISMA forma que el render estático. Corre dentro de WorkspaceContext (lo fija
 * el controlador público); las consultas quedan scopeadas por workspace + site.
 */
final class CollectionRouteResolver implements DynamicRouteResolver
{
    public function resolve(int $siteId, string $path): ?array
    {
        // MVP: rutas de dos segmentos, prefijo/slug.
        $parts = explode('/', trim($path, '/'));
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return null;
        }
        [$prefix, $slug] = $parts;

        $collection = Collection::query()
            ->where('site_id', $siteId)
            ->where('route_prefix', $prefix)
            ->whereNotNull('template_page_id')
            ->first();
        if ($collection === null) {
            return null;
        }

        $entry = Entry::query()
            ->where('collection_id', $collection->id)
            ->where('slug', $slug)
            ->published()
            ->with(['author', 'collection.fields'])
            ->first();
        if ($entry === null) {
            return null;
        }

        $template = Page::query()->whereKey($collection->template_page_id)->first();
        $version = $template?->publishedVersion;
        if ($template === null || $version === null) {
            return null;
        }

        // Schema crudo (preserva objetos) → resolver bindings contra la entry.
        $decoded = json_decode((string) $version->getRawOriginal('schema'));
        $sections = ($decoded instanceof \stdClass && isset($decoded->sections)) ? $decoded->sections : [];
        $resolvedSections = BindingResolver::resolve($sections, EntryBindings::map($entry));

        $site = Site::query()->whereKey($siteId)->first();
        $settings = is_array($site?->settings) ? $site->settings : [];
        $baseUrl = isset($settings['base_url']) && is_string($settings['base_url']) ? rtrim($settings['base_url'], '/') : '';

        return [
            'site' => [
                'id' => $site?->ulid,
                'name' => $site?->name,
            ],
            'page' => [
                'id' => $template->ulid,
                'path' => $path,
                'kind' => Page::KIND_COLLECTION_TEMPLATE,
                'version_id' => $version->ulid,
                'schema_version' => $version->schema_version,
                'sections' => $resolvedSections,
            ],
            'entry' => [
                'id' => $entry->ulid,
                'title' => $entry->title,
                'slug' => $entry->slug,
                'collection' => $collection->handle,
            ],
            'seo' => [
                'title' => $entry->title,
                'canonical' => $baseUrl.$path,
                'robots' => 'index,follow',
            ],
            'published_at' => $entry->published_at?->toIso8601String(),
        ];
    }
}
