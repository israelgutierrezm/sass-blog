<?php

declare(strict_types=1);

namespace App\Modules\Seo\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Seo\Application\SitemapDocument;
use App\Modules\Seo\Application\SitemapGenerator;
use App\Modules\Shared\Domain\Rendering\SiteHostResolver;
use App\Modules\Shared\Domain\Tenancy\WorkspaceContext;
use App\Modules\Sites\Infrastructure\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Superficie pública (SIN auth) del sitemap y robots por sitio (ADR-018). El workspace
 * se deriva del sitio EN EL SERVIDOR y la lectura corre dentro de WorkspaceContext
 * (ADR-006). Las <loc> son absolutas. Base, por orden: el dominio propio desde el que se pide
 * (`?host=`, lo pasa el renderer; sólo si es un dominio ACTIVO de ESTE sitio), la base_url del
 * sitio, o el host del request.
 */
final class SitemapController extends Controller
{
    public function sitemap(Request $request, string $site): Response
    {
        $siteModel = $this->resolveSite($site);
        $baseUrl = $this->baseUrl($request, $siteModel);

        $urls = app(WorkspaceContext::class)->runFor(
            $siteModel->workspace_id,
            fn (): array => app(SitemapGenerator::class)->forSite($siteModel->id),
        );

        return response(SitemapDocument::xml($urls, $baseUrl), 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    public function robots(Request $request, string $site): Response
    {
        $siteModel = $this->resolveSite($site);
        $baseUrl = $this->baseUrl($request, $siteModel);

        return response(SitemapDocument::robots($baseUrl), 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    private function resolveSite(string $ulid): Site
    {
        // Mismo gate que el render público: el sitio debe existir y no estar archivado.
        $site = Site::withoutGlobalScopes()
            ->where('ulid', Str::upper($ulid))
            ->where('status', '!=', Site::STATUS_ARCHIVED)
            ->first();

        abort_if($site === null, 404, 'Sitio no encontrado.');

        return $site;
    }

    private function baseUrl(Request $request, Site $site): string
    {
        // Un sitemap sólo puede listar URLs de su propio host: servido en un dominio propio,
        // la base es ese dominio. Nunca un host arbitrario del cliente: debe estar activo.
        $host = (string) $request->query('host', '');
        if ($host !== '' && app()->bound(SiteHostResolver::class) && app(SiteHostResolver::class)->isActiveHost($site->id, $host)) {
            return 'https://'.rtrim(explode(':', Str::lower(trim($host)))[0], '.');
        }

        $settings = is_array($site->settings) ? $site->settings : [];

        if (isset($settings['base_url']) && is_string($settings['base_url']) && $settings['base_url'] !== '') {
            return rtrim($settings['base_url'], '/');
        }

        return rtrim($request->getSchemeAndHttpHost(), '/');
    }
}
