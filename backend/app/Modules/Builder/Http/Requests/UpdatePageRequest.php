<?php

declare(strict_types=1);

namespace App\Modules\Builder\Http\Requests;

use App\Modules\Builder\Http\Rules\ValidPageSchema;
use App\Modules\Builder\Infrastructure\Models\Page;
use App\Modules\Shared\Domain\Tenancy\WorkspaceContext;
use App\Modules\Sites\Infrastructure\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdatePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $workspaceId = app(WorkspaceContext::class)->idOrNull();
        $siteId = Site::findByUlid((string) $this->route('site'))?->id;
        $pageId = Page::findByUlid((string) $this->route('page'))?->id;

        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'path' => [
                'sometimes', 'string', 'max:255', 'regex:'.StorePageRequest::PATH_REGEX,
                Rule::unique('pages', 'path')
                    ->where('workspace_id', $workspaceId)
                    ->where('site_id', $siteId)
                    ->ignore($pageId),
            ],
            // 'published' NO se fija por PATCH: publicar es una acción propia.
            'status' => ['sometimes', Rule::in([Page::STATUS_DRAFT, Page::STATUS_ARCHIVED])],
            // El schema del draft se valida contra el registry (perfil draft, relajado).
            'schema' => ['sometimes', 'array', new ValidPageSchema('draft')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'path.regex' => 'La ruta debe empezar por "/" y usar minúsculas, números y guiones.',
            'path.unique' => 'Ya existe una página con esta ruta en el sitio.',
        ];
    }
}
