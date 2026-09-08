<?php

declare(strict_types=1);

namespace App\Modules\Builder\Http\Requests;

use App\Modules\Shared\Domain\Tenancy\WorkspaceContext;
use App\Modules\Sites\Infrastructure\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StorePageRequest extends FormRequest
{
    /** Ruta normalizada: '/' o '/segmento(/segmento)*' en minúsculas-kebab. */
    public const PATH_REGEX = '/^\/([a-z0-9]+(?:-[a-z0-9]+)*(?:\/[a-z0-9]+(?:-[a-z0-9]+)*)*)?$/';

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

        return [
            'title' => ['required', 'string', 'max:255'],
            'path' => [
                'required', 'string', 'max:255', 'regex:'.self::PATH_REGEX,
                Rule::unique('pages', 'path')
                    ->where('workspace_id', $workspaceId)
                    ->where('site_id', $siteId),
            ],
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
