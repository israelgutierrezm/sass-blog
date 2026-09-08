<?php

declare(strict_types=1);

namespace App\Modules\Builder\Application;

use App\Modules\Builder\Infrastructure\Models\Page;
use App\Modules\Builder\Infrastructure\Models\PageVersion;

/**
 * Arma el payload que consume el renderer: site, page (con el schema de la
 * versión) y un SEO mínimo derivado (FASE 2; el SEO de primera clase es Fase 4).
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
        $schema = is_array($version->schema) ? $version->schema : [];

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
                'sections' => $schema['sections'] ?? [],
            ],
            'seo' => [
                'title' => $page->title,
                'canonical' => rtrim($baseUrl, '/').$page->path,
                'robots' => $robots,
            ],
            'published_at' => $page->published_at?->toIso8601String(),
        ];
    }
}
