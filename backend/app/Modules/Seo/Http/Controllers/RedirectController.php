<?php

declare(strict_types=1);

namespace App\Modules\Seo\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Seo\Http\Requests\StoreRedirectRequest;
use App\Modules\Seo\Http\Requests\UpdateRedirectRequest;
use App\Modules\Seo\Http\Resources\RedirectResource;
use App\Modules\Seo\Infrastructure\Models\Redirect;
use App\Modules\Sites\Infrastructure\Models\Site;
use App\Modules\Tenancy\Infrastructure\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * CRUD manual de redirects, bajo /workspaces/{ws}/sites/{site}/redirects. El stack
 * (auth + workspace) lo aplica la ruta; la Policy exige `redirect.manage`. Aislamiento
 * por site EXPLÍCITO; `source`/`created_by` los fija el servidor, nunca el cliente.
 */
final class RedirectController extends Controller
{
    public function index(Workspace $workspace, string $site): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Redirect::class);
        $siteModel = $this->resolveSite($site);

        $redirects = Redirect::forSite($siteModel->id)->latest()->paginate(50);

        return RedirectResource::collection($redirects);
    }

    public function store(Workspace $workspace, string $site, StoreRedirectRequest $request): JsonResponse
    {
        $this->authorize('create', Redirect::class);
        $siteModel = $this->resolveSite($site);

        $redirect = new Redirect($request->validated());
        $redirect->site_id = $siteModel->id;
        $redirect->source = Redirect::SOURCE_MANUAL;
        $redirect->created_by = Auth::id();
        $redirect->save();

        return (new RedirectResource($redirect))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Workspace $workspace, string $site, string $redirect, UpdateRedirectRequest $request): RedirectResource
    {
        $siteModel = $this->resolveSite($site);
        $redirectModel = $this->resolveRedirect($siteModel, $redirect);
        $this->authorize('update', $redirectModel);

        $redirectModel->fill($request->validated());
        $redirectModel->save();

        return new RedirectResource($redirectModel->fresh());
    }

    public function destroy(Workspace $workspace, string $site, string $redirect): Response
    {
        $siteModel = $this->resolveSite($site);
        $redirectModel = $this->resolveRedirect($siteModel, $redirect);
        $this->authorize('delete', $redirectModel);

        $redirectModel->delete();

        return response()->noContent();
    }

    private function resolveSite(string $ulid): Site
    {
        $site = Site::findByUlid($ulid);
        abort_if($site === null, 404, 'Sitio no encontrado.');

        return $site;
    }

    private function resolveRedirect(Site $site, string $ulid): Redirect
    {
        $redirect = Redirect::findByUlid($ulid);
        abort_if($redirect === null || $redirect->site_id !== $site->id, 404, 'Redirect no encontrado.');

        return $redirect;
    }
}
