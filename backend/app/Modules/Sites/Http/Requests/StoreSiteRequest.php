<?php

declare(strict_types=1);

namespace App\Modules\Sites\Http\Requests;

use App\Modules\Shared\Domain\Tenancy\WorkspaceContext;
use App\Modules\Sites\Infrastructure\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La autorización fina (permiso site.create) la hace el controlador vía Policy.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $workspaceId = app(WorkspaceContext::class)->idOrNull();

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255', 'alpha_dash',
                // Unicidad DENTRO del workspace (no global).
                Rule::unique('sites', 'slug')->where('workspace_id', $workspaceId),
            ],
            'status' => ['sometimes', Rule::in([
                Site::STATUS_DRAFT, Site::STATUS_PUBLISHED, Site::STATUS_ARCHIVED,
            ])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.unique' => 'Ya existe un sitio con este slug en el workspace.',
        ];
    }
}
