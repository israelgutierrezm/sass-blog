<?php

declare(strict_types=1);

namespace App\Modules\Builder\Http\Resources;

use App\Modules\Builder\Infrastructure\Models\Page;
use App\Modules\Builder\Infrastructure\Models\PageVersion;
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
            // Sólo en `show`: el schema del draft para editar. Se devuelve desde el
            // JSON CRUDO (objetos {} preservados) para que el round-trip del admin no
            // convierta settings/props vacíos en [].
            'draft_schema' => $this->whenLoaded('draftVersion', fn () => self::decodeSchema($this->draftVersion)),
            'has_unpublished_changes' => $this->whenLoaded('draftVersion', function (): bool {
                if ($this->published_version_id === null) {
                    return true;
                }

                // != (no !==): MySQL JSON reordena claves de objeto.
                return $this->draftVersion?->schema != $this->publishedVersion?->schema;
            }),
        ];
    }

    /** Decodifica el schema desde el JSON crudo (objetos {} preservados). */
    private static function decodeSchema(?PageVersion $version): mixed
    {
        if ($version === null) {
            return null;
        }

        return json_decode((string) $version->getRawOriginal('schema'));
    }
}
