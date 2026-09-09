<?php

declare(strict_types=1);

namespace App\Modules\Media\Http\Resources;

use App\Modules\Media\Infrastructure\Models\MediaAsset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin MediaAsset
 *
 * Expone URLs públicas (calculadas por el backend); nunca el disk/path interno.
 */
final class MediaAssetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'url' => Storage::disk($this->disk)->url($this->path),
            'original_filename' => $this->original_filename,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'width' => $this->width,
            'height' => $this->height,
            'alt' => $this->alt,
            'title' => $this->title,
            'status' => $this->status,
            'variants' => $this->whenLoaded('variants', fn () => $this->variants
                ->mapWithKeys(fn ($variant) => [$variant->variant => Storage::disk($variant->disk)->url($variant->path)])),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
