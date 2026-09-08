<?php

declare(strict_types=1);

namespace App\Modules\Sites\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sites\Application\CreateSite;
use App\Modules\Sites\Http\Requests\StoreSiteRequest;
use App\Modules\Sites\Http\Resources\SiteResource;
use App\Modules\Sites\Infrastructure\Models\Site;
use App\Modules\Tenancy\Infrastructure\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sites de un workspace. El middleware 'workspace' ya fijó el contexto y validó la
 * membresía; el global scope garantiza que sólo se ven/crean sites del workspace
 * activo.
 */
final class SiteController extends Controller
{
    public function index(Workspace $workspace): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Site::class);

        return SiteResource::collection(Site::query()->latest()->get());
    }

    public function store(Workspace $workspace, StoreSiteRequest $request, CreateSite $createSite): JsonResponse
    {
        $this->authorize('create', Site::class);

        // workspace_id lo rellena BelongsToWorkspace desde el contexto: nunca del
        // cliente. CreateSite emite SiteCreated (Content siembra el preset de artículos).
        $site = $createSite->handle($request->safe()->only(['name', 'slug', 'status']));

        return (new SiteResource($site))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Workspace $workspace, string $site): SiteResource
    {
        // Resolución manual (no route-binding): el binding correría antes de fijar
        // el contexto y el global scope lanzaría. Aquí el contexto ya está fijado,
        // así que un ULID de otro workspace no resuelve (404).
        $model = Site::findByUlid($site);
        abort_if($model === null, 404, 'Sitio no encontrado.');

        $this->authorize('view', $model);

        return new SiteResource($model);
    }
}
