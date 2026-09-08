<?php

declare(strict_types=1);

namespace App\Modules\Builder\Http\Resources;

use App\Modules\Builder\Infrastructure\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Page
 */
final class PageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'title' => $this->title,
            'path' => $this->path,
            'status' => $this->status,
            'published_at' => $this->published_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            // Sólo en `show`: el schema del draft para editar.
            'draft_schema' => $this->whenLoaded('draftVersion', fn () => $this->draftVersion?->schema),
            'has_unpublished_changes' => $this->whenLoaded('draftVersion', function (): bool {
                if ($this->published_version_id === null) {
                    return true;
                }

                // != (no !==): MySQL JSON reordena claves de objeto.
                return $this->draftVersion?->schema != $this->publishedVersion?->schema;
            }),
        ];
    }
}
