<?php

declare(strict_types=1);

namespace App\Modules\Seo\Http\Resources;

use App\Modules\Seo\Infrastructure\Models\Redirect;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Redirect
 */
final class RedirectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'from_path' => $this->from_path,
            'to_path' => $this->to_path,
            'status' => $this->status,
            'source' => $this->source,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
