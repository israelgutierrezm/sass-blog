<?php

declare(strict_types=1);

namespace App\Modules\Navigation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Navigation\Http\Requests\StoreMenuRequest;
use App\Modules\Navigation\Http\Requests\UpdateMenuRequest;
use App\Modules\Navigation\Http\Resources\MenuResource;
use App\Modules\Navigation\Infrastructure\Models\Menu;
use App\Modules\Sites\Infrastructure\Models\Site;
use App\Modules\Tenancy\Infrastructure\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * CRUD de menús, bajo /workspaces/{ws}/sites/{site}/menus. El stack (auth + workspace)
 * lo aplica la ruta; la Policy exige `menu.manage`. Aislamiento por site EXPLÍCITO.
 */
final class MenuController extends Controller
{
    public function index(Workspace $workspace, string $site): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Menu::class);
        $siteModel = $this->resolveSite($site);

        return MenuResource::collection(Menu::forSite($siteModel->id)->latest()->get());
    }

    public function store(Workspace $workspace, string $site, StoreMenuRequest $request): JsonResponse
    {
        $this->authorize('create', Menu::class);
        $siteModel = $this->resolveSite($site);

        $menu = new Menu($request->validated());
        $menu->site_id = $siteModel->id;
        $menu->created_by = Auth::id();
        $menu->save();

        return (new MenuResource($menu))->response()->setStatusCode(201);
    }

    public function show(Workspace $workspace, string $site, string $menu): MenuResource
    {
        $siteModel = $this->resolveSite($site);
        $menuModel = $this->resolveMenu($siteModel, $menu);
        $this->authorize('view', $menuModel);

        return new MenuResource($menuModel->load('items'));
    }

    public function update(Workspace $workspace, string $site, string $menu, UpdateMenuRequest $request): MenuResource
    {
        $siteModel = $this->resolveSite($site);
        $menuModel = $this->resolveMenu($siteModel, $menu);
        $this->authorize('update', $menuModel);

        $menuModel->fill($request->validated());
        $menuModel->save();

        return new MenuResource($menuModel->fresh()->load('items'));
    }

    public function destroy(Workspace $workspace, string $site, string $menu): Response
    {
        $siteModel = $this->resolveSite($site);
        $menuModel = $this->resolveMenu($siteModel, $menu);
        $this->authorize('delete', $menuModel);

        $menuModel->delete(); // cascade: ítems

        return response()->noContent();
    }

    private function resolveSite(string $ulid): Site
    {
        $site = Site::findByUlid($ulid);
        abort_if($site === null, 404, 'Sitio no encontrado.');

        return $site;
    }

    private function resolveMenu(Site $site, string $ulid): Menu
    {
        $menu = Menu::findByUlid($ulid);
        abort_if($menu === null || $menu->site_id !== $site->id, 404, 'Menú no encontrado.');

        return $menu;
    }
}
