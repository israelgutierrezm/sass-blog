<?php

declare(strict_types=1);

namespace App\Modules\Publishing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Publishing\Application\Jobs\BuildStaticSite;
use App\Modules\Publishing\Application\SitePublishedState;
use App\Modules\Publishing\Http\Resources\DeploymentResource;
use App\Modules\Publishing\Infrastructure\Models\Deployment;
use App\Modules\Sites\Infrastructure\Models\Site;
use App\Modules\Tenancy\Infrastructure\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * API de deployments (export estático), bajo /workspaces/{ws}/sites/{site}/deployments.
 * El stack (auth + workspace + capability:site.export.static) lo aplica la ruta; la Policy
 * exige `site.publish`. Disparar encola el build (nunca corre en el request).
 */
final class DeploymentController extends Controller
{
    public function index(Workspace $workspace, string $site): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Deployment::class);
        $siteModel = $this->resolveSite($site);

        return DeploymentResource::collection(
            Deployment::forSite($siteModel->id)->latest()->paginate(20),
        );
    }

    public function store(Workspace $workspace, string $site): JsonResponse
    {
        $this->authorize('create', Deployment::class);
        $siteModel = $this->resolveSite($site);

        $hash = app(SitePublishedState::class)->hash($siteModel->id);

        // Idempotencia (ADR-019): si el estado publicado no cambió y ya hay un artefacto
        // exitoso, se devuelve ese en vez de reconstruir.
        $existing = Deployment::forSite($siteModel->id)
            ->where('published_hash', $hash)
            ->where('status', Deployment::STATUS_SUCCESS)
            ->whereNotNull('artifact_ref')
            ->latest()
            ->first();

        if ($existing !== null) {
            return (new DeploymentResource($existing))->response()->setStatusCode(200);
        }

        $deployment = new Deployment;
        $deployment->site_id = $siteModel->id;
        $deployment->target = Deployment::TARGET_STATIC;
        $deployment->published_hash = $hash;
        $deployment->triggered_by = Auth::id();
        $deployment->save();

        BuildStaticSite::dispatch($deployment->id, $siteModel->workspace_id);

        // 202: el build corre en cola; el cliente sondea el estado.
        return (new DeploymentResource($deployment))->response()->setStatusCode(202);
    }

    public function show(Workspace $workspace, string $site, string $deployment): DeploymentResource
    {
        $siteModel = $this->resolveSite($site);
        $deploymentModel = $this->resolveDeployment($siteModel, $deployment);
        $this->authorize('view', $deploymentModel);

        return new DeploymentResource($deploymentModel);
    }

    public function download(Workspace $workspace, string $site, string $deployment): StreamedResponse
    {
        $siteModel = $this->resolveSite($site);
        $deploymentModel = $this->resolveDeployment($siteModel, $deployment);
        $this->authorize('view', $deploymentModel);

        $disk = (string) config('sassblog.publishing.disk', 'local');
        abort_if(
            $deploymentModel->artifact_ref === null || ! Storage::disk($disk)->exists($deploymentModel->artifact_ref),
            404,
            'Artefacto no disponible.',
        );

        return Storage::disk($disk)->download($deploymentModel->artifact_ref, "site-{$siteModel->ulid}.zip");
    }

    private function resolveSite(string $ulid): Site
    {
        $site = Site::findByUlid($ulid);
        abort_if($site === null, 404, 'Sitio no encontrado.');

        return $site;
    }

    private function resolveDeployment(Site $site, string $ulid): Deployment
    {
        $deployment = Deployment::findByUlid($ulid);
        abort_if($deployment === null || $deployment->site_id !== $site->id, 404, 'Deployment no encontrado.');

        return $deployment;
    }
}
