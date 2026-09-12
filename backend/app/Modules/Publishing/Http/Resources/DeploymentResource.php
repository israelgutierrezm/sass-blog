<?php

declare(strict_types=1);

namespace App\Modules\Publishing\Http\Resources;

use App\Modules\Publishing\Infrastructure\Models\Deployment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Deployment
 */
final class DeploymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'target' => $this->target,
            'status' => $this->status,
            // `artifact_ref` (ruta interna del disk) NO se expone; la descarga va por
            // endpoint firmado. Sólo se indica si hay artefacto y su tamaño.
            'has_artifact' => $this->artifact_ref !== null,
            'bytes' => $this->bytes,
            'error' => $this->error,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
