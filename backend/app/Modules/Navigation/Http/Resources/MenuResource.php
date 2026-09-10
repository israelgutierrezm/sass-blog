<?php

declare(strict_types=1);

namespace App\Modules\Navigation\Http\Resources;

use App\Modules\Navigation\Infrastructure\Models\Menu;
use App\Modules\Navigation\Infrastructure\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * @mixin Menu
 */
final class MenuResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'handle' => $this->handle,
            'name' => $this->name,
            // Árbol resuelto SÓLO desde los ítems ya cargados (una consulta, sin N+1).
            'items' => $this->whenLoaded('items', fn () => self::tree($this->items, null)),
            'created_at' => $this->created_at,
        ];
    }

    /**
     * Construye el árbol jerárquico en memoria desde la colección plana de ítems.
     *
     * @param  Collection<int, MenuItem>  $items
     * @return list<array<string, mixed>>
     */
    private static function tree(Collection $items, ?int $parentId): array
    {
        return $items
            ->where('parent_id', $parentId)
            ->sortBy('position')
            ->values()
            ->map(fn (MenuItem $item): array => [
                'id' => $item->ulid,
                'label' => $item->label,
                'link_type' => $item->link_type,
                'target' => $item->target_ulid,
                'url' => $item->url,
                'position' => $item->position,
                'children' => self::tree($items, $item->id),
            ])
            ->all();
    }
}
