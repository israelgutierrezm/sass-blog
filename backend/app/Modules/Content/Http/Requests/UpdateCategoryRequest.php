<?php

declare(strict_types=1);

namespace App\Modules\Content\Http\Requests;

use App\Modules\Content\Infrastructure\Models\Category;
use App\Modules\Content\Infrastructure\Models\Collection;
use App\Modules\Shared\Domain\Tenancy\WorkspaceContext;
use App\Modules\Sites\Infrastructure\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateCategoryRequest extends FormRequest
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
        $categoryId = Category::findByUlid((string) $this->route('category'))?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes', 'required', 'string', 'max:255', 'alpha_dash',
                Rule::unique('categories', 'slug')
                    ->where('workspace_id', $workspaceId)
                    ->where('site_id', $siteId)
                    ->where('collection_id', $collectionId)
                    ->ignore($categoryId),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.unique' => 'Ya existe una categoría con este slug en la colección.',
        ];
    }
}
