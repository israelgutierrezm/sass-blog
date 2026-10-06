<?php

declare(strict_types=1);

namespace App\Modules\Newsletter\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Newsletter\Http\Resources\SubscriberResource;
use App\Modules\Newsletter\Http\Resources\SubscriberStatsResource;
use App\Modules\Newsletter\Infrastructure\Models\Subscriber;
use App\Modules\Sites\Infrastructure\Models\Site;
use App\Modules\Tenancy\Infrastructure\Models\Workspace;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Gestión de suscriptores de un sitio (ADR-022). Auth + workspace (contexto) + Policy
 * (`newsletter.manage`). Construir la lista es de todos los planes (sin capability).
 */
final class SubscriberController extends Controller
{
    public function index(Workspace $workspace, string $site): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Subscriber::class);
        $siteModel = $this->resolveSite($site);

        return SubscriberResource::collection(
            Subscriber::forSite($siteModel->id)->latest()->paginate(50)
        );
    }

    /**
     * Conteo por estado. Agrega en BD (cubierto por el índice workspace_id+site_id+status): el
     * listado está paginado y no sirve para contar.
     */
    public function stats(Workspace $workspace, string $site): SubscriberStatsResource
    {
        $this->authorize('viewAny', Subscriber::class);
        $siteModel = $this->resolveSite($site);

        $counts = Subscriber::forSite($siteModel->id)
            ->toBase()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return new SubscriberStatsResource([
            'pending' => (int) ($counts[Subscriber::STATUS_PENDING] ?? 0),
            'confirmed' => (int) ($counts[Subscriber::STATUS_CONFIRMED] ?? 0),
            'unsubscribed' => (int) ($counts[Subscriber::STATUS_UNSUBSCRIBED] ?? 0),
        ]);
    }

    public function destroy(Workspace $workspace, string $site, string $subscriber): Response
    {
        $siteModel = $this->resolveSite($site);
        $model = Subscriber::findByUlid($subscriber);
        abort_if($model === null || $model->site_id !== $siteModel->id, 404, 'Suscriptor no encontrado.');
        $this->authorize('delete', $model);

        $model->delete();

        return response()->noContent();
    }

    private function resolveSite(string $ulid): Site
    {
        $site = Site::findByUlid($ulid);
        abort_if($site === null, 404, 'Sitio no encontrado.');

        return $site;
    }
}
