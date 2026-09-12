<?php

declare(strict_types=1);

namespace App\Modules\Publishing\Application;

use App\Modules\Builder\Application\RenderedPage;
use App\Modules\Builder\Infrastructure\Models\Page;
use App\Modules\Seo\Application\SitemapGenerator;
use App\Modules\Shared\Domain\Rendering\DynamicRouteResolver;
use App\Modules\Sites\Infrastructure\Models\Site;
use Illuminate\Support\Facades\Storage;

/**
 * Compone el BUILD MANIFEST (input del render Node, ADR-019) de un sitio: por cada URL
 * pública (enumerada con SitemapGenerator) el MISMO payload de `/render`
 * (RenderedPage::payload para páginas; DynamicRouteResolver para detalles de colección),
 * más la lista de media referenciada. Un solo motor: el estático reusa exactamente lo del
 * dinámico. Corre dentro del WorkspaceContext (lo fija el job).
 */
final class BuildManifest
{
    public function __construct(private readonly SitemapGenerator $sitemap) {}

    /**
     * @return array{site: array<string,mixed>, pages: list<array<string,mixed>>, media: list<string>}
     */
    public function forSite(Site $site): array
    {
        $settings = is_array($site->settings) ? $site->settings : [];
        $baseUrl = isset($settings['base_url']) && is_string($settings['base_url']) ? rtrim($settings['base_url'], '/') : '';

        $pages = [];
        foreach ($this->sitemap->forSite($site->id) as $url) {
            $render = $this->renderPayload($site, $url->path);
            if ($render !== null) {
                $pages[] = ['path' => $url->path, 'render' => $render];
            }
        }

        return [
            'site' => ['ulid' => $site->ulid, 'name' => $site->name, 'base_url' => $baseUrl],
            'pages' => $pages,
            'media' => $this->collectMedia($pages),
        ];
    }

    /**
     * Resuelve el payload de una ruta como el render público: página estática publicada
     * primero, luego el detalle dinámico de colección. Sin auth ni 404 (offline).
     *
     * @return array<string,mixed>|null
     */
    private function renderPayload(Site $site, string $path): ?array
    {
        $page = Page::query()->where('site_id', $site->id)->where('path', $path)->first();
        if ($page !== null && $page->published_version_id !== null) {
            $version = $page->publishedVersion;
            if ($version !== null) {
                return RenderedPage::payload($page, $version, 'index,follow');
            }
        }

        if (app()->bound(DynamicRouteResolver::class)) {
            return app(DynamicRouteResolver::class)->resolve($site->id, $path);
        }

        return null;
    }

    /**
     * URLs de media referenciadas en los payloads (para copiarlas al artefacto en 5.4).
     * Escaneo desacoplado (Publishing no depende de Media): busca las URLs bajo la ruta
     * pública del disco de media. MVP: heurística sobre el JSON serializado.
     *
     * @param  list<array<string,mixed>>  $pages
     * @return list<string>
     */
    private function collectMedia(array $pages): array
    {
        $disk = (string) config('sassblog.media.disk', 'public');
        $base = rtrim((string) Storage::disk($disk)->url('media'), '/');
        $marker = '/'.ltrim((string) (parse_url($base, PHP_URL_PATH) ?: $base), '/');

        $json = (string) json_encode($pages, JSON_UNESCAPED_SLASHES);
        preg_match_all('#"([^"]*'.preg_quote($marker, '#').'/[^"]+)"#', $json, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }
}
