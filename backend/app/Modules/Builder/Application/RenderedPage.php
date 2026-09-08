<?php

declare(strict_types=1);

namespace App\Modules\Builder\Application;

use App\Modules\Builder\Infrastructure\Models\Page;
use App\Modules\Builder\Infrastructure\Models\PageVersion;
use App\Modules\Shared\Domain\Rendering\SectionDataResolver;
use App\Modules\Shared\Domain\Rendering\SectionResolution;

/**
 * Arma el payload que consume el renderer: site, page (con el schema de la
 * versión), el sidecar `resolved` de las secciones dinámicas y un SEO mínimo
 * derivado (FASE 2; el SEO de primera clase es Fase 4).
 */
final class RenderedPage
{
    /**
     * @return array<string, mixed>
     */
    public static function payload(Page $page, PageVersion $version, string $robots): array
    {
        $site = $page->site;
        $settings = is_array($site->settings) ? $site->settings : [];
        $baseUrl = isset($settings['base_url']) && is_string($settings['base_url']) ? $settings['base_url'] : '';

        // Desde el JSON CRUDO: preserva los objetos {} de settings/props vacíos.
        $decoded = json_decode((string) $version->getRawOriginal('schema'));
        $sections = ($decoded instanceof \stdClass && isset($decoded->sections)) ? $decoded->sections : [];

        $resolver = app()->bound(SectionDataResolver::class) ? app(SectionDataResolver::class) : null;
        $resolved = SectionResolution::forSections($sections, $resolver, [
            'workspace_id' => $page->workspace_id,
            'site_id' => $page->site_id,
        ]);

        return [
            'site' => [
                'id' => $site->ulid,
                'name' => $site->name,
            ],
            'page' => [
                'id' => $page->ulid,
                'path' => $page->path,
                'version_id' => $version->ulid,
                'schema_version' => $version->schema_version,
                'sections' => $sections,
            ],
            'resolved' => $resolved,
            'seo' => [
                'title' => $page->title,
                'canonical' => rtrim($baseUrl, '/').$page->path,
                'robots' => $robots,
            ],
            'published_at' => $page->published_at?->toIso8601String(),
        ];
    }
}
