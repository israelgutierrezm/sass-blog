<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Resources;

use App\Modules\Tenancy\Infrastructure\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Workspace
 */
final class WorkspaceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'name' => $this->name,
            'slug' => $this->slug,
            'personal' => $this->personal,
            // El rol del usuario en el workspace, cuando se listó vía la membresía.
            'role' => $this->whenPivotLoaded('workspace_members', fn () => $this->pivot->role),
            'created_at' => $this->created_at,
        ];
    }
}
