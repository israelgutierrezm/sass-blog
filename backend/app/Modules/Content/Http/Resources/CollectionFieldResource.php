<?php

declare(strict_types=1);

namespace App\Modules\Content\Http\Resources;

use App\Modules\Content\Infrastructure\Models\CollectionField;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CollectionField
 */
final class CollectionFieldResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type->value,
            'required' => $this->required,
            'config' => $this->config,
            'position' => $this->position,
            'related_collection' => $this->whenLoaded('relatedCollection', fn () => $this->relatedCollection?->ulid),
        ];
    }
}
