<?php

declare(strict_types=1);

namespace App\Modules\Navigation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Navigation\Http\Requests\StoreMenuItemRequest;
use App\Modules\Navigation\Http\Requests\UpdateMenuItemRequest;
use App\Modules\Navigation\Http\Resources\MenuItemResource;
use App\Modules\Navigation\Infrastructure\Models\Menu;
use App\Modules\Navigation\Infrastructure\Models\MenuItem;
use App\Modules\Sites\Infrastructure\Models\Site;
use App\Modules\Tenancy\Infrastructure\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * CRUD de ítems de menú, anidado bajo el menú. Gestionar ítems = `update` del menú
 * (Policy `menu.manage`). Los ULID de referencia (`target`, `parent`) se mapean a las
 * columnas internas; se mantiene la coherencia entre `link_type` y target/url.
 */
final class MenuItemController extends Controller
{
    public function store(Workspace $workspace, string $site, string $menu, StoreMenuItemRequest $request): JsonResponse
    {
        $siteModel = $this->resolveSite($site);
        $menuModel = $this->resolveMenu($siteModel, $menu);
        $this->authorize('update', $menuModel);

        $data = $request->validated();
        $item = new MenuItem;
        $item->site_id = $siteModel->id;
        $item->menu_id = $menuModel->id;
        $item->label = $data['label'];
        $item->link_type = $data['link_type'];
        $item->position = $data['position'] ?? 0;
        $item->parent_id = $this->resolveParentId($data['parent'] ?? null);
        $this->applyTarget($item, $data['target'] ?? null, $data['url'] ?? null);
        $item->save();

        return (new MenuItemResource($item->load('parent')))->response()->setStatusCode(201);
    }

    public function update(Workspace $workspace, string $site, string $menu, string $item, UpdateMenuItemRequest $request): MenuItemResource
    {
        $siteModel = $this->resolveSite($site);
        $menuModel = $this->resolveMenu($siteModel, $menu);
        $this->authorize('update', $menuModel);
        $itemModel = $this->resolveItem($menuModel, $item);

        $data = $request->validated();

        if (array_key_exists('label', $data)) {
            $itemModel->label = $data['label'];
        }
        if (array_key_exists('position', $data)) {
            $itemModel->position = $data['position'];
        }
        if (array_key_exists('link_type', $data)) {
            $itemModel->link_type = $data['link_type'];
        }
        if (array_key_exists('parent', $data)) {
            $itemModel->parent_id = $this->resolveParentId($data['parent']);
        }

        // Coherencia tipo↔destino: al enviar target/url, o al cambiar el tipo, se re-aplica.
        if (array_key_exists('target', $data) || array_key_exists('url', $data) || array_key_exists('link_type', $data)) {
            $this->applyTarget(
                $itemModel,
                array_key_exists('target', $data) ? $data['target'] : $itemModel->target_ulid,
                array_key_exists('url', $data) ? $data['url'] : $itemModel->url,
            );
        }

        $itemModel->save();

        return new MenuItemResource($itemModel->fresh()->load('parent'));
    }

    public function destroy(Workspace $workspace, string $site, string $menu, string $item): Response
    {
        $siteModel = $this->resolveSite($site);
        $menuModel = $this->resolveMenu($siteModel, $menu);
        $this->authorize('update', $menuModel);
        $itemModel = $this->resolveItem($menuModel, $item);

        $itemModel->delete(); // cascade: hijos

        return response()->noContent();
    }

    /** Mantiene target_ulid/url coherentes con link_type (limpia el que no aplica). */
    private function applyTarget(MenuItem $item, ?string $target, ?string $url): void
    {
        $isReferential = in_array($item->link_type, StoreMenuItemRequest::REFERENTIAL, true);
        $item->target_ulid = $isReferential ? $target : null;
        $item->url = $item->link_type === MenuItem::LINK_URL ? $url : null;
    }

    private function resolveParentId(?string $ulid): ?int
    {
        return $ulid !== null && $ulid !== '' ? MenuItem::findByUlid($ulid)?->id : null;
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

    private function resolveItem(Menu $menu, string $ulid): MenuItem
    {
        $item = MenuItem::findByUlid($ulid);
        abort_if($item === null || $item->menu_id !== $menu->id, 404, 'Elemento no encontrado.');

        return $item;
    }
}
