<?php

declare(strict_types=1);

namespace App\Modules\Builder\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Builder\Application\RenderedPage;
use App\Modules\Builder\Infrastructure\Models\Page;
use App\Modules\Shared\Domain\Rendering\DynamicRouteResolver;
use App\Modules\Shared\Domain\Tenancy\WorkspaceContext;
use App\Modules\Sites\Infrastructure\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Superficie pública del renderer (SIN auth). El workspace se deriva del sitio EN
 * EL SERVIDOR y la lectura corre dentro de WorkspaceContext::runFor (ADR-006): el
 * cliente aporta sólo el ULID público; workspace_id/site_id jamás llegan de él.
 */
final class PublicPageController extends Controller
{
    public function render(Request $request, string $site): JsonResponse
    {
        // FASE 2: el gate público es a nivel de PÁGINA (published_version_id). El
        // sitio sólo debe existir y no estar archivado; el gating por estado del
        // SITIO se difiere a cuando exista el ciclo de vida de publicación del sitio.
        $siteModel = Site::withoutGlobalScopes()
            ->where('ulid', Str::upper($site))
            ->where('status', '!=', Site::STATUS_ARCHIVED)
            ->first();

        abort_if($siteModel === null, 404, 'Sitio no encontrado.');

        $path = $this->normalizePath((string) $request->query('path', '/'));

        return app(WorkspaceContext::class)->runFor($siteModel->workspace_id, function () use ($siteModel, $path): JsonResponse {
            // 1. Estático primero: una Page con ese path exacto, publicada.
            $page = Page::query()
                ->where('site_id', $siteModel->id)
                ->where('path', $path)
                ->first();

            if ($page !== null && $page->published_version_id !== null) {
                $version = $page->publishedVersion;
                if ($version !== null) {
                    return response()
                        ->json(['data' => RenderedPage::payload($page, $version, 'index,follow')])
                        ->header('ETag', '"'.$version->ulid.'"')
                        ->header('Cache-Control', 'public, max-age=60');
                }
            }

            // 2. Dinámico: detalle de colección, SÓLO si Content enlazó el resolver de
            // kernel (si no, el render queda sólo-estático; Builder no depende de Content).
            if (app()->bound(DynamicRouteResolver::class)) {
                $payload = app(DynamicRouteResolver::class)->resolve($siteModel->id, $path);
                if ($payload !== null) {
                    return response()
                        ->json(['data' => $payload])
                        ->header('ETag', '"'.md5((string) json_encode($payload['page'] ?? [])).'"')
                        ->header('Cache-Control', 'public, max-age=60');
                }
            }

            abort(404, 'Página no publicada.');
        });
    }

    public function preview(Request $request, string $site, string $page): JsonResponse
    {
        // La URL viene firmada (middleware 'signed'). El sitio puede no estar
        // publicado (es el dueño previsualizando su draft).
        $siteModel = Site::withoutGlobalScopes()->where('ulid', Str::upper($site))->first();
        abort_if($siteModel === null, 404, 'Sitio no encontrado.');

        return app(WorkspaceContext::class)->runFor($siteModel->workspace_id, function () use ($siteModel, $page): JsonResponse {
            $pageModel = Page::query()
                ->where('site_id', $siteModel->id)
                ->where('ulid', Str::upper($page))
                ->first();

            abort_if($pageModel === null, 404, 'Página no encontrada.');

            $version = $pageModel->draftVersion;
            abort_if($version === null, 404);

            return response()
                ->json(['data' => RenderedPage::payload($pageModel, $version, 'noindex,nofollow')])
                ->header('X-Robots-Tag', 'noindex, nofollow')
                ->header('Cache-Control', 'no-store');
        });
    }

    private function normalizePath(string $path): string
    {
        $path = '/'.ltrim(trim($path), '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }
}
