<?php

declare(strict_types=1);

namespace App\Modules\Builder\Http\Resources;

use App\Modules\Builder\Infrastructure\Models\PageVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PageVersion
 */
final class PageVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'version_number' => $this->version_number,
            'status' => $this->status,
            'schema_version' => $this->schema_version,
            'schema' => $this->schema,
            'published_at' => $this->published_at,
        ];
    }
}
