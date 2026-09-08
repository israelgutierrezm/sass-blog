<?php

declare(strict_types=1);

namespace App\Modules\Content\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Content\Application\SlugGenerator;
use App\Modules\Content\Http\Requests\StoreAuthorRequest;
use App\Modules\Content\Http\Requests\UpdateAuthorRequest;
use App\Modules\Content\Http\Resources\AuthorResource;
use App\Modules\Content\Infrastructure\Models\Author;
use App\Modules\Sites\Infrastructure\Models\Site;
use App\Modules\Tenancy\Infrastructure\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * API admin de autores, a nivel de site. El middleware 'workspace' fijó el contexto
 * y 'capability:cms.collections' el gating por plan; site y autor se resuelven con
 * findByUlid (nunca route-binding). Aislamiento por site EXPLÍCITO (ADR-005).
 */
final class AuthorController extends Controller
{
    public function index(Workspace $workspace, string $site): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Author::class);
        $siteModel = $this->resolveSite($site);

        return AuthorResource::collection(
            Author::query()->where('site_id', $siteModel->id)->orderBy('position')->orderBy('name')->get()
        );
    }

    public function store(Workspace $workspace, string $site, StoreAuthorRequest $request): JsonResponse
    {
        $this->authorize('create', Author::class);
        $siteModel = $this->resolveSite($site);

        $author = new Author;
        $author->site_id = $siteModel->id;
        $author->fill($request->safe()->only(['name', 'bio', 'avatar_url', 'email', 'links', 'position']));
        $author->slug = $this->resolveSlug($request, $siteModel);
        $author->created_by = Auth::id();
        $author->save();

        return (new AuthorResource($author))->response()->setStatusCode(201);
    }

    public function show(Workspace $workspace, string $site, string $author): AuthorResource
    {
        $siteModel = $this->resolveSite($site);
        $authorModel = $this->resolveAuthor($siteModel, $author);
        $this->authorize('view', $authorModel);

        return new AuthorResource($authorModel);
    }

    public function update(Workspace $workspace, string $site, string $author, UpdateAuthorRequest $request): AuthorResource
    {
        $siteModel = $this->resolveSite($site);
        $authorModel = $this->resolveAuthor($siteModel, $author);
        $this->authorize('update', $authorModel);

        $authorModel->fill($request->safe()->only(['name', 'slug', 'bio', 'avatar_url', 'email', 'links', 'position']));
        $authorModel->save();

        return new AuthorResource($authorModel->fresh());
    }

    public function destroy(Workspace $workspace, string $site, string $author): Response
    {
        $siteModel = $this->resolveSite($site);
        $authorModel = $this->resolveAuthor($siteModel, $author);
        $this->authorize('delete', $authorModel);

        $authorModel->delete();

        return response()->noContent();
    }

    private function resolveSlug(StoreAuthorRequest $request, Site $site): string
    {
        if ($request->filled('slug')) {
            return $request->string('slug')->toString();
        }

        return app(SlugGenerator::class)->unique(
            $request->string('name')->toString(),
            // withTrashed: el índice único incluye soft-deleted, así se evita colisión.
            fn (string $candidate): bool => Author::withTrashed()
                ->where('site_id', $site->id)
                ->where('slug', $candidate)
                ->exists(),
            'autor',
        );
    }

    private function resolveSite(string $ulid): Site
    {
        $site = Site::findByUlid($ulid);
        abort_if($site === null, 404, 'Sitio no encontrado.');

        return $site;
    }

    private function resolveAuthor(Site $site, string $ulid): Author
    {
        $author = Author::findByUlid($ulid);
        abort_if($author === null || $author->site_id !== $site->id, 404, 'Autor no encontrado.');

        return $author;
    }
}
