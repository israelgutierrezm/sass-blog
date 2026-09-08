<?php

declare(strict_types=1);

namespace App\Modules\Content\Http\Requests;

use App\Modules\Content\Domain\CollectionKind;
use App\Modules\Content\Domain\Fields\FieldType;
use App\Modules\Shared\Domain\Tenancy\WorkspaceContext;
use App\Modules\Sites\Infrastructure\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreCollectionRequest extends FormRequest
{
    /** Formato de key de campo (ADR-010): empieza por letra, minúsculas/números/_. */
    public const KEY_REGEX = '/^[a-z][a-z0-9_]*$/';

    /** Slug de ruta: minúsculas-kebab. */
    public const ROUTE_PREFIX_REGEX = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

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
            'name' => ['required', 'string', 'max:255'],
            'name_singular' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            // handle NO se valida como único: CreateCollection lo desambigua (-2, -3…).
            'handle' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:'.self::KEY_REGEX],
            'kind' => ['sometimes', Rule::in([CollectionKind::Generic->value, CollectionKind::Article->value])],
            'route_prefix' => [
                'sometimes', 'nullable', 'string', 'max:255', 'regex:'.self::ROUTE_PREFIX_REGEX,
                Rule::unique('collections', 'route_prefix')
                    ->where('workspace_id', $workspaceId)
                    ->where('site_id', $siteId),
            ],
            'fields' => ['sometimes', 'array'],
            'fields.*.key' => ['required', 'string', 'max:255', 'distinct', 'regex:'.self::KEY_REGEX],
            'fields.*.label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'fields.*.type' => ['required', Rule::in(array_map(fn (FieldType $t) => $t->value, FieldType::cases()))],
            'fields.*.required' => ['sometimes', 'boolean'],
            'fields.*.config' => ['sometimes', 'nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fields.*.key.regex' => 'La clave del campo debe empezar por letra y usar sólo minúsculas, números y guion bajo.',
            'route_prefix.unique' => 'Ya existe una colección con este prefijo de ruta en el sitio.',
        ];
    }
}
