<?php

declare(strict_types=1);

namespace App\Modules\Content\Http\Resources;

use App\Modules\Content\Infrastructure\Models\Entry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Entry
 *
 * `path` lo calcula el backend (route_prefix + slug); el frontend nunca arma rutas.
 * El payload de campos se expone como `values` (la columna es `data`): una clave
 * `data` en el recurso colisionaría con el envoltorio `data` de Laravel y dejaría la
 * respuesta SIN envolver.
 */
final class EntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'title' => $this->title,
            'slug' => $this->slug,
            'status' => $this->status,
            'path' => $this->publicPath(),
            'values' => $this->data,
            'author' => $this->whenLoaded('author', fn () => $this->author === null ? null : [
                'id' => $this->author->ulid,
                'name' => $this->author->name,
                'slug' => $this->author->slug,
            ]),
            'categories' => $this->whenLoaded('categories', fn () => $this->categories->map(fn ($category) => [
                'id' => $category->ulid,
                'name' => $category->name,
                'slug' => $category->slug,
            ])->values()),
            'published_at' => $this->published_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /** Ruta pública de detalle: /{route_prefix}/{slug}, o null si la colección no tiene prefijo. */
    private function publicPath(): ?string
    {
        $prefix = $this->whenLoaded('collection', fn () => $this->collection->route_prefix);

        if (! is_string($prefix) || $prefix === '') {
            return null;
        }

        return '/'.$prefix.'/'.$this->slug;
    }
}
