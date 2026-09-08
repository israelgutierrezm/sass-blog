<?php

declare(strict_types=1);

namespace App\Modules\Content\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Content\Application\SlugGenerator;
use App\Modules\Content\Http\Requests\StoreCategoryRequest;
use App\Modules\Content\Http\Requests\UpdateCategoryRequest;
use App\Modules\Content\Http\Resources\CategoryResource;
use App\Modules\Content\Infrastructure\Models\Category;
use App\Modules\Content\Infrastructure\Models\Collection;
use App\Modules\Sites\Infrastructure\Models\Site;
use App\Modules\Tenancy\Infrastructure\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * API admin de categorías, anidadas bajo su colección (collection_id es intrínseco:
 * unique por colección). Resolución manual por ULID tras el contexto; aislamiento
 * por site y por colección EXPLÍCITO.
 */
final class CategoryController extends Controller
{
    public function index(Workspace $workspace, string $site, string $collection): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Category::class);
        $siteModel = $this->resolveSite($site);
        $collectionModel = $this->resolveCollection($siteModel, $collection);

        return CategoryResource::collection(
            Category::query()->where('collection_id', $collectionModel->id)->orderBy('position')->orderBy('name')->get()
        );
    }

    public function store(Workspace $workspace, string $site, string $collection, StoreCategoryRequest $request): JsonResponse
    {
        $this->authorize('create', Category::class);
        $siteModel = $this->resolveSite($site);
        $collectionModel = $this->resolveCollection($siteModel, $collection);

        $category = new Category;
        $category->site_id = $siteModel->id;
        $category->collection_id = $collectionModel->id;
        $category->fill($request->safe()->only(['name', 'description', 'position']));
        $category->slug = $this->resolveSlug($request, $siteModel, $collectionModel);
        $category->created_by = Auth::id();
        $category->save();

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    public function show(Workspace $workspace, string $site, string $collection, string $category): CategoryResource
    {
        $siteModel = $this->resolveSite($site);
        $collectionModel = $this->resolveCollection($siteModel, $collection);
        $categoryModel = $this->resolveCategory($collectionModel, $category);
        $this->authorize('view', $categoryModel);

        return new CategoryResource($categoryModel);
    }

    public function update(Workspace $workspace, string $site, string $collection, string $category, UpdateCategoryRequest $request): CategoryResource
    {
        $siteModel = $this->resolveSite($site);
        $collectionModel = $this->resolveCollection($siteModel, $collection);
        $categoryModel = $this->resolveCategory($collectionModel, $category);
        $this->authorize('update', $categoryModel);

        $categoryModel->fill($request->safe()->only(['name', 'slug', 'description', 'position']));
        $categoryModel->save();

        return new CategoryResource($categoryModel->fresh());
    }

    public function destroy(Workspace $workspace, string $site, string $collection, string $category): Response
    {
        $siteModel = $this->resolveSite($site);
        $collectionModel = $this->resolveCollection($siteModel, $collection);
        $categoryModel = $this->resolveCategory($collectionModel, $category);
        $this->authorize('delete', $categoryModel);

        // Sin soft-deletes: soltar el pivote antes de borrar (no deja huérfanos).
        $categoryModel->entries()->detach();
        $categoryModel->delete();

        return response()->noContent();
    }

    private function resolveSlug(StoreCategoryRequest $request, Site $site, Collection $collection): string
    {
        if ($request->filled('slug')) {
            return $request->string('slug')->toString();
        }

        return app(SlugGenerator::class)->unique(
            $request->string('name')->toString(),
            fn (string $candidate): bool => Category::query()
                ->where('collection_id', $collection->id)
                ->where('slug', $candidate)
                ->exists(),
            'categoria',
        );
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

    private function resolveCategory(Collection $collection, string $ulid): Category
    {
        $category = Category::findByUlid($ulid);
        abort_if($category === null || $category->collection_id !== $collection->id, 404, 'Categoría no encontrada.');

        return $category;
    }
}
