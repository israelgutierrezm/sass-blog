<?php

declare(strict_types=1);

namespace App\Modules\Navigation\Http\Requests;

use App\Modules\Shared\Domain\Tenancy\WorkspaceContext;
use App\Modules\Sites\Infrastructure\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreMenuRequest extends FormRequest
{
    /** Handle en minúsculas-kebab (primary, footer, main-nav…). */
    public const HANDLE_REGEX = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

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
            'handle' => [
                'required', 'string', 'max:50', 'regex:'.self::HANDLE_REGEX,
                Rule::unique('menus', 'handle')
                    ->where('workspace_id', $workspaceId)
                    ->where('site_id', $siteId),
            ],
            'name' => ['required', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'handle.regex' => 'El identificador debe usar minúsculas, números y guiones.',
            'handle.unique' => 'Ya existe un menú con ese identificador en el sitio.',
        ];
    }
}
