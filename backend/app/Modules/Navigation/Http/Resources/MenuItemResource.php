<?php

declare(strict_types=1);

namespace App\Modules\Navigation\Http\Resources;

use App\Modules\Navigation\Infrastructure\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MenuItem
 */
final class MenuItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'label' => $this->label,
            'link_type' => $this->link_type,
            'target' => $this->target_ulid,
            'url' => $this->url,
            'parent' => $this->whenLoaded('parent', fn () => $this->parent?->ulid),
            'position' => $this->position,
        ];
    }
}
