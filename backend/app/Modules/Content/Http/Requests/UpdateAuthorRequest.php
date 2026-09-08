<?php

declare(strict_types=1);

namespace App\Modules\Content\Http\Requests;

use App\Modules\Content\Http\Rules\SafeUrl;
use App\Modules\Content\Infrastructure\Models\Author;
use App\Modules\Shared\Domain\Tenancy\WorkspaceContext;
use App\Modules\Sites\Infrastructure\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateAuthorRequest extends FormRequest
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
        $authorId = Author::findByUlid((string) $this->route('author'))?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes', 'required', 'string', 'max:255', 'alpha_dash',
                Rule::unique('authors', 'slug')
                    ->where('workspace_id', $workspaceId)
                    ->where('site_id', $siteId)
                    ->ignore($authorId),
            ],
            'bio' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'avatar_url' => ['sometimes', 'nullable', new SafeUrl],
            'email' => ['sometimes', 'nullable', 'email:rfc', 'max:255'],
            'links' => ['sometimes', 'nullable', 'array'],
            'links.*' => ['string', new SafeUrl],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.unique' => 'Ya existe un autor con este slug en el sitio.',
        ];
    }
}
