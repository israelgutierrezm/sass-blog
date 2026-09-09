<?php

declare(strict_types=1);

namespace App\Modules\Media\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Media\Application\StoreMediaAsset;
use App\Modules\Media\Http\Requests\StoreMediaRequest;
use App\Modules\Media\Http\Requests\UpdateMediaRequest;
use App\Modules\Media\Http\Resources\MediaAssetResource;
use App\Modules\Media\Infrastructure\Models\MediaAsset;
use App\Modules\Sites\Infrastructure\Models\Site;
use App\Modules\Tenancy\Infrastructure\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * API de la librería de medios, bajo /workspaces/{ws}/sites/{site}/media. El stack
 * (auth + workspace + capability:media.library) lo aplica la ruta. Aislamiento por
 * site EXPLÍCITO; resolución por ULID tras el contexto.
 */
final class MediaController extends Controller
{
    public function index(Workspace $workspace, string $site, Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', MediaAsset::class);
        $siteModel = $this->resolveSite($site);

        $query = MediaAsset::query()->where('site_id', $siteModel->id)->with('variants')->latest();

        if ($request->string('type')->toString() === 'image') {
            $query->where('mime_type', 'like', 'image/%');
        }

        return MediaAssetResource::collection($query->paginate(24));
    }

    public function store(Workspace $workspace, string $site, StoreMediaRequest $request): JsonResponse
    {
        $this->authorize('create', MediaAsset::class);
        $siteModel = $this->resolveSite($site);

        $asset = app(StoreMediaAsset::class)->handle($siteModel, $request->file('file'), Auth::id());

        return (new MediaAssetResource($asset->load('variants')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Workspace $workspace, string $site, string $asset): MediaAssetResource
    {
        $siteModel = $this->resolveSite($site);
        $assetModel = $this->resolveAsset($siteModel, $asset);
        $this->authorize('view', $assetModel);

        return new MediaAssetResource($assetModel->load('variants'));
    }

    public function update(Workspace $workspace, string $site, string $asset, UpdateMediaRequest $request): MediaAssetResource
    {
        $siteModel = $this->resolveSite($site);
        $assetModel = $this->resolveAsset($siteModel, $asset);
        $this->authorize('update', $assetModel);

        $assetModel->fill($request->safe()->only(['alt', 'title']));
        $assetModel->save();

        return new MediaAssetResource($assetModel->fresh()->load('variants'));
    }

    public function destroy(Workspace $workspace, string $site, string $asset): Response
    {
        $siteModel = $this->resolveSite($site);
        $assetModel = $this->resolveAsset($siteModel, $asset);
        $this->authorize('delete', $assetModel);

        // Soft delete: los binarios se limpian en un job (deuda MVP declarada).
        $assetModel->delete();

        return response()->noContent();
    }

    private function resolveSite(string $ulid): Site
    {
        $site = Site::findByUlid($ulid);
        abort_if($site === null, 404, 'Sitio no encontrado.');

        return $site;
    }

    private function resolveAsset(Site $site, string $ulid): MediaAsset
    {
        $asset = MediaAsset::findByUlid($ulid);
        abort_if($asset === null || $asset->site_id !== $site->id, 404, 'Archivo no encontrado.');

        return $asset;
    }
}
