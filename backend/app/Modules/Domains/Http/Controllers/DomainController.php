<?php

declare(strict_types=1);

namespace App\Modules\Domains\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Domains\Application\Jobs\VerifyDomain;
use App\Modules\Domains\Http\Requests\StoreDomainRequest;
use App\Modules\Domains\Http\Resources\DomainResource;
use App\Modules\Domains\Infrastructure\Models\SiteDomain;
use App\Modules\Sites\Infrastructure\Models\Site;
use App\Modules\Tenancy\Infrastructure\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * CRUD de dominios propios, bajo /workspaces/{ws}/sites/{site}/domains. El stack (auth +
 * workspace + capability:site.custom_domain) lo aplica la ruta; la Policy exige
 * `domain.manage`. Conectar un dominio encola su verificación (nunca en el request).
 */
final class DomainController extends Controller
{
    public function index(Workspace $workspace, string $site): AnonymousResourceCollection
    {
        $this->authorize('viewAny', SiteDomain::class);
        $siteModel = $this->resolveSite($site);

        return DomainResource::collection(SiteDomain::forSite($siteModel->id)->latest()->get());
    }

    public function store(Workspace $workspace, string $site, StoreDomainRequest $request): JsonResponse
    {
        $this->authorize('create', SiteDomain::class);
        $siteModel = $this->resolveSite($site);

        $domain = new SiteDomain($request->validated());
        $domain->site_id = $siteModel->id;
        $domain->verification_token = Str::random(32);
        $domain->created_by = Auth::id();
        // El primer dominio del sitio es el primario por defecto.
        $domain->is_primary = SiteDomain::forSite($siteModel->id)->count() === 0;
        $domain->save();

        VerifyDomain::dispatch($domain->id, $siteModel->workspace_id);

        return (new DomainResource($domain))->response()->setStatusCode(201);
    }

    public function recheck(Workspace $workspace, string $site, string $domain): DomainResource
    {
        $siteModel = $this->resolveSite($site);
        $domainModel = $this->resolveDomain($siteModel, $domain);
        $this->authorize('update', $domainModel);

        $domainModel->update(['status' => SiteDomain::STATUS_PENDING]);
        VerifyDomain::dispatch($domainModel->id, $siteModel->workspace_id);

        return new DomainResource($domainModel->fresh());
    }

    public function setPrimary(Workspace $workspace, string $site, string $domain): DomainResource
    {
        $siteModel = $this->resolveSite($site);
        $domainModel = $this->resolveDomain($siteModel, $domain);
        $this->authorize('update', $domainModel);

        SiteDomain::forSite($siteModel->id)->where('id', '!=', $domainModel->id)->update(['is_primary' => false]);
        $domainModel->update(['is_primary' => true]);

        return new DomainResource($domainModel->fresh());
    }

    public function destroy(Workspace $workspace, string $site, string $domain): Response
    {
        $siteModel = $this->resolveSite($site);
        $domainModel = $this->resolveDomain($siteModel, $domain);
        $this->authorize('delete', $domainModel);

        $domainModel->delete();

        return response()->noContent();
    }

    private function resolveSite(string $ulid): Site
    {
        $site = Site::findByUlid($ulid);
        abort_if($site === null, 404, 'Sitio no encontrado.');

        return $site;
    }

    private function resolveDomain(Site $site, string $ulid): SiteDomain
    {
        $domain = SiteDomain::findByUlid($ulid);
        abort_if($domain === null || $domain->site_id !== $site->id, 404, 'Dominio no encontrado.');

        return $domain;
    }
}
