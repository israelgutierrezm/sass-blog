<?php

declare(strict_types=1);

namespace App\Modules\Content\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Content\Application\EntryWorkflow;
use App\Modules\Content\Application\PublishEntry;
use App\Modules\Content\Application\SlugGenerator;
use App\Modules\Content\Application\Validation\EntryDataValidator;
use App\Modules\Content\Http\Requests\ApproveEntryRequest;
use App\Modules\Content\Http\Requests\RequestChangesRequest;
use App\Modules\Content\Http\Requests\StoreEntryRequest;
use App\Modules\Content\Http\Requests\UpdateEntryRequest;
use App\Modules\Content\Http\Resources\EntryResource;
use App\Modules\Content\Infrastructure\Models\Author;
use App\Modules\Content\Infrastructure\Models\Category;
use App\Modules\Content\Infrastructure\Models\Collection;
use App\Modules\Content\Infrastructure\Models\Entry;
use App\Modules\Sites\Infrastructure\Models\Site;
use App\Modules\Tenancy\Infrastructure\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * API admin de entries (borrador), anidada bajo su colección. El `data` se valida
 * dinámicamente contra el schema (perfil draft); publicar es una acción propia
 * (s8). Aislamiento por site y por colección EXPLÍCITO.
 */
final class EntryController extends Controller
{
    public function index(Workspace $workspace, string $site, string $collection): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Entry::class);
        $siteModel = $this->resolveSite($site);
        $collectionModel = $this->resolveCollection($siteModel, $collection);

        return EntryResource::collection(
            Entry::query()
                ->where('collection_id', $collectionModel->id)
                ->with(['author', 'categories', 'collection'])
                ->latest()
                ->get()
        );
    }

    public function store(Workspace $workspace, string $site, string $collection, StoreEntryRequest $request): JsonResponse
    {
        $this->authorize('create', Entry::class);
        $siteModel = $this->resolveSite($site);
        $collectionModel = $this->resolveCollection($siteModel, $collection);

        $entry = new Entry;
        $entry->site_id = $siteModel->id;
        $entry->collection_id = $collectionModel->id;
        $entry->title = $request->string('title')->toString();
        $entry->slug = $this->resolveSlug($request, $collectionModel);
        $entry->author_id = $this->resolveAuthorId($siteModel, $request->input('author'));
        if ($request->has('values')) {
            $entry->data = (array) $request->input('values', []);
        }
        $entry->created_by = Auth::id();
        $entry->updated_by = Auth::id();
        $entry->save();

        if ($request->has('category_ids')) {
            $entry->syncCategories($this->resolveCategoryIds($collectionModel, (array) $request->input('category_ids', [])));
        }

        return (new EntryResource($entry->load(['author', 'categories', 'collection'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Workspace $workspace, string $site, string $collection, string $entry): EntryResource
    {
        $siteModel = $this->resolveSite($site);
        $collectionModel = $this->resolveCollection($siteModel, $collection);
        $entryModel = $this->resolveEntry($collectionModel, $entry);
        $this->authorize('view', $entryModel);

        return new EntryResource($entryModel->load(['author', 'categories', 'collection']));
    }

    public function update(Workspace $workspace, string $site, string $collection, string $entry, UpdateEntryRequest $request): EntryResource
    {
        $siteModel = $this->resolveSite($site);
        $collectionModel = $this->resolveCollection($siteModel, $collection);
        $entryModel = $this->resolveEntry($collectionModel, $entry);
        $this->authorize('update', $entryModel);

        if ($request->has('title')) {
            $entryModel->title = $request->string('title')->toString();
        }
        if ($request->has('slug')) {
            $entryModel->slug = $request->string('slug')->toString();
        }
        if ($request->has('author')) {
            $entryModel->author_id = $this->resolveAuthorId($siteModel, $request->input('author'));
        }
        if ($request->has('values')) {
            $entryModel->data = (array) $request->input('values', []);
        }
        $entryModel->updated_by = Auth::id();
        $entryModel->save();

        if ($request->has('category_ids')) {
            $entryModel->syncCategories($this->resolveCategoryIds($collectionModel, (array) $request->input('category_ids', [])));
        }

        return new EntryResource($entryModel->fresh()->load(['author', 'categories', 'collection']));
    }

    public function publish(Workspace $workspace, string $site, string $collection, string $entry): EntryResource
    {
        $siteModel = $this->resolveSite($site);
        $collectionModel = $this->resolveCollection($siteModel, $collection);
        $entryModel = $this->resolveEntry($collectionModel, $entry);
        $this->authorize('publish', $entryModel);

        // Revalidar con perfil PUBLISH: los campos required deben estar presentes.
        $errors = app(EntryDataValidator::class)->validate($collectionModel->fields, $entryModel->data, 'publish');
        if ($errors !== []) {
            throw ValidationException::withMessages([
                'values' => ['No se puede publicar: '.$errors[0]['message']],
            ]);
        }

        $published = app(PublishEntry::class)->handle($entryModel, Auth::id());

        return new EntryResource($published->load(['author', 'categories', 'collection']));
    }

    /** Flujo editorial (ADR-023). Enviar a revisión: redactor (`entry.update`). */
    public function submitReview(Workspace $workspace, string $site, string $collection, string $entry): EntryResource
    {
        $entryModel = $this->workflowEntry($site, $collection, $entry, 'update');

        return $this->workflowResult(app(EntryWorkflow::class)->submitForReview($entryModel, Auth::id()));
    }

    /** Retirar de revisión: redactor (`entry.update`). */
    public function withdrawReview(Workspace $workspace, string $site, string $collection, string $entry): EntryResource
    {
        $entryModel = $this->workflowEntry($site, $collection, $entry, 'update');

        return $this->workflowResult(app(EntryWorkflow::class)->withdraw($entryModel, Auth::id()));
    }

    /** Aprobar (publicar ya o programar): editor (`entry.publish`). */
    public function approve(Workspace $workspace, string $site, string $collection, string $entry, ApproveEntryRequest $request): EntryResource
    {
        $siteModel = $this->resolveSite($site);
        $collectionModel = $this->resolveCollection($siteModel, $collection);
        $entryModel = $this->resolveEntry($collectionModel, $entry);
        $this->authorize('publish', $entryModel);

        // Aprobar lleva el contenido en vivo (ya o programado): revalidar perfil PUBLISH.
        $errors = app(EntryDataValidator::class)->validate($collectionModel->fields, $entryModel->data, 'publish');
        if ($errors !== []) {
            throw ValidationException::withMessages(['values' => ['No se puede aprobar: '.$errors[0]['message']]]);
        }

        return $this->workflowResult(app(EntryWorkflow::class)->approve($entryModel, $request->date('publish_at'), Auth::id()));
    }

    /** Pedir cambios: editor (`entry.publish`). Devuelve a borrador con nota. */
    public function requestChanges(Workspace $workspace, string $site, string $collection, string $entry, RequestChangesRequest $request): EntryResource
    {
        $entryModel = $this->workflowEntry($site, $collection, $entry, 'publish');

        return $this->workflowResult(app(EntryWorkflow::class)->requestChanges($entryModel, $request->string('note')->toString(), Auth::id()));
    }

    /** Resuelve site→colección→entry y autoriza la habilidad dada. */
    private function workflowEntry(string $site, string $collection, string $entry, string $ability): Entry
    {
        $siteModel = $this->resolveSite($site);
        $collectionModel = $this->resolveCollection($siteModel, $collection);
        $entryModel = $this->resolveEntry($collectionModel, $entry);
        $this->authorize($ability, $entryModel);

        return $entryModel;
    }

    private function workflowResult(Entry $entry): EntryResource
    {
        return new EntryResource($entry->load(['author', 'categories', 'collection']));
    }

    private function resolveSlug(StoreEntryRequest $request, Collection $collection): string
    {
        if ($request->filled('slug')) {
            return $request->string('slug')->toString();
        }

        return app(SlugGenerator::class)->unique(
            $request->string('title')->toString(),
            fn (string $candidate): bool => Entry::query()
                ->where('collection_id', $collection->id)
                ->where('slug', $candidate)
                ->exists(),
            'entrada',
        );
    }

    private function resolveAuthorId(Site $site, ?string $ulid): ?int
    {
        if ($ulid === null || $ulid === '') {
            return null;
        }

        return Author::query()
            ->where('site_id', $site->id)
            ->where('ulid', Str::upper($ulid))
            ->value('id');
    }

    /**
     * @param  array<int, mixed>  $ulids
     * @return array<int, int>
     */
    private function resolveCategoryIds(Collection $collection, array $ulids): array
    {
        if ($ulids === []) {
            return [];
        }

        return Category::query()
            ->where('collection_id', $collection->id)
            ->whereIn('ulid', array_map(fn ($u) => Str::upper((string) $u), $ulids))
            ->pluck('id')
            ->all();
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

    private function resolveEntry(Collection $collection, string $ulid): Entry
    {
        $entry = Entry::findByUlid($ulid);
        abort_if($entry === null || $entry->collection_id !== $collection->id, 404, 'Entrada no encontrada.');

        return $entry;
    }
}
