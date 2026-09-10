<?php

declare(strict_types=1);

namespace App\Modules\Navigation\Http\Requests;

use App\Modules\Navigation\Infrastructure\Models\Menu;
use App\Modules\Shared\Domain\Tenancy\WorkspaceContext;
use App\Modules\Sites\Infrastructure\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateMenuRequest extends FormRequest
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
        $menuId = Menu::findByUlid((string) $this->route('menu'))?->id;

        return [
            'handle' => [
                'sometimes', 'string', 'max:50', 'regex:'.StoreMenuRequest::HANDLE_REGEX,
                Rule::unique('menus', 'handle')
                    ->where('workspace_id', $workspaceId)
                    ->where('site_id', $siteId)
                    ->ignore($menuId),
            ],
            'name' => ['sometimes', 'string', 'max:120'],
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
