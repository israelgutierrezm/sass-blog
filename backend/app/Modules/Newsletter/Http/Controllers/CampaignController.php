<?php

declare(strict_types=1);

namespace App\Modules\Newsletter\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Newsletter\Application\Jobs\SendCampaign;
use App\Modules\Newsletter\Http\Requests\StoreCampaignRequest;
use App\Modules\Newsletter\Http\Requests\UpdateCampaignRequest;
use App\Modules\Newsletter\Http\Resources\CampaignResource;
use App\Modules\Newsletter\Infrastructure\Models\Campaign;
use App\Modules\Sites\Infrastructure\Models\Site;
use App\Modules\Tenancy\Infrastructure\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

/**
 * Campañas de un sitio (ADR-022). Auth + workspace + Policy (`newsletter.manage`). Gestionar
 * campañas (crear/editar/listar) es de todos los planes; ENVIAR requiere `capability:newsletter.send`
 * (Pro), aplicada en la ruta de `send`.
 */
final class CampaignController extends Controller
{
    public function index(Workspace $workspace, string $site): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Campaign::class);
        $siteModel = $this->resolveSite($site);

        return CampaignResource::collection(Campaign::forSite($siteModel->id)->latest()->get());
    }

    public function store(Workspace $workspace, string $site, StoreCampaignRequest $request): JsonResponse
    {
        $this->authorize('create', Campaign::class);
        $siteModel = $this->resolveSite($site);

        $campaign = new Campaign($request->validated());
        $campaign->site_id = $siteModel->id;
        $campaign->created_by = Auth::id();
        $campaign->save();

        return (new CampaignResource($campaign))->response()->setStatusCode(201);
    }

    public function update(Workspace $workspace, string $site, string $campaign, UpdateCampaignRequest $request): CampaignResource
    {
        $siteModel = $this->resolveSite($site);
        $campaignModel = $this->resolveCampaign($siteModel, $campaign);
        $this->authorize('update', $campaignModel);
        abort_unless($campaignModel->isDraft(), 422, 'Sólo se puede editar una campaña en borrador.');

        $campaignModel->update($request->validated());

        return new CampaignResource($campaignModel->fresh());
    }

    public function send(Workspace $workspace, string $site, string $campaign): CampaignResource
    {
        $siteModel = $this->resolveSite($site);
        $campaignModel = $this->resolveCampaign($siteModel, $campaign);
        $this->authorize('update', $campaignModel);
        abort_unless($campaignModel->isDraft(), 422, 'La campaña ya no es un borrador.');

        SendCampaign::dispatch($campaignModel->id, $siteModel->workspace_id);

        return new CampaignResource($campaignModel->fresh());
    }

    private function resolveSite(string $ulid): Site
    {
        $site = Site::findByUlid($ulid);
        abort_if($site === null, 404, 'Sitio no encontrado.');

        return $site;
    }

    private function resolveCampaign(Site $site, string $ulid): Campaign
    {
        $campaign = Campaign::findByUlid($ulid);
        abort_if($campaign === null || $campaign->site_id !== $site->id, 404, 'Campaña no encontrada.');

        return $campaign;
    }
}
