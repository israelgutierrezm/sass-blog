<?php

declare(strict_types=1);

namespace App\Modules\Content\Http\Requests;

use App\Modules\Content\Http\Rules\ValidEntryData;
use App\Modules\Content\Infrastructure\Models\Collection;
use App\Modules\Shared\Domain\Tenancy\WorkspaceContext;
use App\Modules\Sites\Infrastructure\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreEntryRequest extends FormRequest
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
        $collection = Collection::findByUlid((string) $this->route('collection'));
        $collectionId = $collection?->id;

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'sometimes', 'nullable', 'string', 'max:255', 'alpha_dash',
                Rule::unique('entries', 'slug')
                    ->where('workspace_id', $workspaceId)
                    ->where('site_id', $siteId)
                    ->where('collection_id', $collectionId),
            ],
            'author' => [
                'sometimes', 'nullable', 'string',
                Rule::exists('authors', 'ulid')
                    ->where('workspace_id', $workspaceId)
                    ->where('site_id', $siteId)
                    ->whereNull('deleted_at'),
            ],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => [
                'string',
                Rule::exists('categories', 'ulid')
                    ->where('workspace_id', $workspaceId)
                    ->where('site_id', $siteId)
                    ->where('collection_id', $collectionId),
            ],
            // Perfil draft: todo opcional; publish revalida en su acción propia (s8).
            // Clave `values` (la columna es `data`; evita la colisión con el envoltorio).
            'values' => array_merge(
                ['sometimes', 'array'],
                $collection !== null ? [new ValidEntryData($collection, 'draft')] : [],
            ),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.unique' => 'Ya existe una entrada con este slug en la colección.',
            'author.exists' => 'El autor no pertenece a este sitio.',
            'category_ids.*.exists' => 'Alguna categoría no pertenece a esta colección.',
        ];
    }
}
