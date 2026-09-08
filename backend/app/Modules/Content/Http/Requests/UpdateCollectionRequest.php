<?php

declare(strict_types=1);

namespace App\Modules\Content\Http\Requests;

use App\Modules\Content\Infrastructure\Models\Collection;
use App\Modules\Shared\Domain\Tenancy\WorkspaceContext;
use App\Modules\Sites\Infrastructure\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Actualiza SÓLO metadatos de la colección. La mutación de campos (añadir/quitar/
 * reordenar) se difiere: afecta a los datos de entries existentes y necesita
 * migración de contenido (constructor visual de campos, fase posterior).
 */
final class UpdateCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la Policy decide en el controlador
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $workspaceId = app(WorkspaceContext::class)->idOrNull();
        $siteId = Site::findByUlid((string) $this->route('site'))?->id;
        $collectionId = Collection::findByUlid((string) $this->route('collection'))?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'name_singular' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'route_prefix' => [
                'sometimes', 'nullable', 'string', 'max:255', 'regex:'.StoreCollectionRequest::ROUTE_PREFIX_REGEX,
                Rule::unique('collections', 'route_prefix')
                    ->where('workspace_id', $workspaceId)
                    ->where('site_id', $siteId)
                    ->ignore($collectionId),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'route_prefix.unique' => 'Ya existe una colección con este prefijo de ruta en el sitio.',
        ];
    }
}
