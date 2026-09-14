<?php

declare(strict_types=1);

namespace App\Modules\Builder\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Builder\Application\CreatePage;
use App\Modules\Builder\Application\PublishPage;
use App\Modules\Builder\Application\SaveDraft;
use App\Modules\Builder\Application\Schema\PageSchemaValidator;
use App\Modules\Builder\Http\Requests\StorePageRequest;
use App\Modules\Builder\Http\Requests\UpdatePageRequest;
use App\Modules\Builder\Http\Resources\PageResource;
use App\Modules\Builder\Infrastructure\Models\Page;
use App\Modules\Shared\Domain\Capabilities\Capabilities;
use App\Modules\Shared\Domain\Capabilities\Capability;
use App\Modules\Sites\Infrastructure\Models\Site;
use App\Modules\Tenancy\Infrastructure\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

/**
 * API admin de páginas, anidada bajo /workspaces/{workspace}/sites/{site}. El
 * middleware 'workspace' fijó el contexto; el site y la page se resuelven con
 * findByUlid (nunca route-binding: correría antes del contexto). El aislamiento
 * por site es EXPLÍCITO (page->site_id === site->id), ADR-005.
 */
final class PageController extends Controller
{
    public function index(Workspace $workspace, string $site): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Page::class);
        $siteModel = $this->resolveSite($site);

        return PageResource::collection(
            Page::query()->where('site_id', $siteModel->id)->latest()->get()
        );
    }

    public function store(Workspace $workspace, string $site, StorePageRequest $request): JsonResponse
    {
        $this->authorize('create', Page::class);
        $siteModel = $this->resolveSite($site);

        $page = app(CreatePage::class)->handle(
            $siteModel,
            $request->string('title')->toString(),
            $request->string('path')->toString(),
            Auth::id(),
        );

        return (new PageResource($page->load('draftVersion')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Workspace $workspace, string $site, string $page): PageResource
    {
        $siteModel = $this->resolveSite($site);
        $pageModel = $this->resolvePage($siteModel, $page);
        $this->authorize('view', $pageModel);

        return new PageResource($pageModel->load(['draftVersion', 'publishedVersion']));
    }

    public function update(Workspace $workspace, string $site, string $page, UpdatePageRequest $request): PageResource
    {
        $siteModel = $this->resolveSite($site);
        $pageModel = $this->resolvePage($siteModel, $page);
        $this->authorize('update', $pageModel);

        $meta = $request->safe()->only(['title', 'path', 'status']);
        if ($meta !== []) {
            $pageModel->fill($meta)->save();
        }

        if ($request->has('schema')) {
            // Objetos del JSON crudo (preserva {} de settings/props vacíos).
            $schema = data_get(json_decode((string) $request->getContent()), 'schema')
                ?? $request->validated('schema');
            $this->guardFeaturedCapability($schema);
            app(SaveDraft::class)->handle($pageModel, $schema);
        }

        return new PageResource($pageModel->fresh()->load(['draftVersion', 'publishedVersion']));
    }

    public function publish(Workspace $workspace, string $site, string $page): PageResource
    {
        $siteModel = $this->resolveSite($site);
        $pageModel = $this->resolvePage($siteModel, $page);
        $this->authorize('publish', $pageModel);

        // El draft debe pasar el perfil PUBLISH (al menos una sección, todo válido).
        // Se valida el JSON CRUDO almacenado (objetos preservados), no el cast assoc.
        $draft = $pageModel->draftVersion;
        $rawSchema = $draft?->getRawOriginal('schema');
        $errors = app(PageSchemaValidator::class)->validateJson(
            is_string($rawSchema) ? $rawSchema : '{}',
            'publish',
        );
        if ($errors !== []) {
            throw ValidationException::withMessages([
                'schema' => ['No se puede publicar: '.$errors[0]['message']],
            ]);
        }

        $published = app(PublishPage::class)->handle($pageModel, Auth::id());

        return new PageResource($published->load(['draftVersion', 'publishedVersion']));
    }

    public function previewLink(Workspace $workspace, string $site, string $page): JsonResponse
    {
        $siteModel = $this->resolveSite($site);
        $pageModel = $this->resolvePage($siteModel, $page);
        $this->authorize('update', $pageModel);

        $expiresAt = now()->addMinutes(15);
        $url = URL::temporarySignedRoute(
            'api.v1.public.preview',
            $expiresAt,
            ['site' => $siteModel->ulid, 'page' => $pageModel->ulid],
        );

        return response()->json(['url' => $url, 'expires_at' => $expiresAt->toIso8601String()]);
    }

    /**
     * Gating de portadas (ADR-024): usar una sección `featured` exige `publisher.frontpages`.
     * Se valida AL GUARDAR (el servidor decide, no el Builder). Lanza CapabilityDeniedException
     * (→ 403) si el plan no la incluye.
     */
    private function guardFeaturedCapability(mixed $schema): void
    {
        $sections = (array) data_get($schema, 'sections', []);
        $usesFeatured = collect($sections)->contains(fn ($section) => data_get($section, 'type') === 'featured');

        if ($usesFeatured) {
            app(Capabilities::class)->authorize(Capability::PublisherFrontpages);
        }
    }

    private function resolveSite(string $ulid): Site
    {
        $site = Site::findByUlid($ulid);
        abort_if($site === null, 404, 'Sitio no encontrado.');

        return $site;
    }

    private function resolvePage(Site $site, string $ulid): Page
    {
        $page = Page::findByUlid($ulid);
        // Aislamiento cross-site DENTRO del workspace (ADR-005).
        abort_if($page === null || $page->site_id !== $site->id, 404, 'Página no encontrada.');

        return $page;
    }
}
