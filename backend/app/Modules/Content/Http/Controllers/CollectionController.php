<?php

declare(strict_types=1);

namespace App\Modules\Content\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Content\Application\CreateCollection;
use App\Modules\Content\Http\Requests\StoreCollectionRequest;
use App\Modules\Content\Http\Requests\UpdateCollectionRequest;
use App\Modules\Content\Http\Resources\CollectionResource;
use App\Modules\Content\Infrastructure\Models\Collection;
use App\Modules\Sites\Infrastructure\Models\Site;
use App\Modules\Tenancy\Infrastructure\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

/**
 * API admin de colecciones, bajo /workspaces/{ws}/sites/{site}/collections. El
 * schema es estructural: owner/admin crean/editan; la mutación de campos se difiere
 * (sólo metadatos en update). Aislamiento por site EXPLÍCITO.
 */
final class CollectionController extends Controller
{
    public function index(Workspace $workspace, string $site): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Collection::class);
        $siteModel = $this->resolveSite($site);

        return CollectionResource::collection(
            Collection::query()->where('site_id', $siteModel->id)->with('fields')->latest()->get()
        );
    }

    public function store(Workspace $workspace, string $site, StoreCollectionRequest $request): JsonResponse
    {
        $this->authorize('create', Collection::class);
        $siteModel = $this->resolveSite($site);

        $attributes = $request->safe()->only(['handle', 'name', 'name_singular', 'description', 'kind', 'route_prefix']);
        $attributes['created_by'] = Auth::id();

        /** @var list<array<string, mixed>> $fields */
        $fields = $request->validated('fields', []);

        $collection = app(CreateCollection::class)->handle($siteModel, $attributes, $fields);

        return (new CollectionResource($collection->load('fields')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Workspace $workspace, string $site, string $collection): CollectionResource
    {
        $siteModel = $this->resolveSite($site);
        $collectionModel = $this->resolveCollection($siteModel, $collection);
        $this->authorize('view', $collectionModel);

        return new CollectionResource($collectionModel->load('fields'));
    }

    public function update(Workspace $workspace, string $site, string $collection, UpdateCollectionRequest $request): CollectionResource
    {
        $siteModel = $this->resolveSite($site);
        $collectionModel = $this->resolveCollection($siteModel, $collection);
        $this->authorize('update', $collectionModel);

        $collectionModel->fill($request->safe()->only(['name', 'name_singular', 'description', 'route_prefix']));
        $collectionModel->save();

        return new CollectionResource($collectionModel->fresh()->load('fields'));
    }

    private function resolveSite(string $ulid): Site
    {
        $site = Site::findByUlid($ulid);
        abort_if($site === null, 404, 'Sitio no encontrado.');

        return $site;
    }

    private function resolveCollection(Site $site, string $ulid): Collection
    {
        $collection = Collection::findByUlid($ulid);
        abort_if($collection === null || $collection->site_id !== $site->id, 404, 'Colección no encontrada.');

        return $collection;
    }
}
