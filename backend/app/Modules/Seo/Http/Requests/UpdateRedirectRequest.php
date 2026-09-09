<?php

declare(strict_types=1);

namespace App\Modules\Seo\Http\Requests;

use App\Modules\Seo\Infrastructure\Models\Redirect;
use App\Modules\Shared\Domain\Tenancy\WorkspaceContext;
use App\Modules\Sites\Infrastructure\Models\Site;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateRedirectRequest extends FormRequest
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
        $redirectId = Redirect::findByUlid((string) $this->route('redirect'))?->id;

        return [
            'from_path' => [
                'sometimes', 'string', 'max:2048', 'regex:'.StoreRedirectRequest::PATH_REGEX,
                Rule::unique('redirects', 'from_path')
                    ->where('workspace_id', $workspaceId)
                    ->where('site_id', $siteId)
                    ->ignore($redirectId),
            ],
            'to_path' => ['sometimes', 'string', 'max:2048', 'regex:'.StoreRedirectRequest::PATH_REGEX, 'different:from_path'],
            'status' => ['sometimes', Rule::in([301, 302])],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(StoreRedirectRequest::normalizePaths($this));
    }

    /**
     * `different:from_path` sólo compara contra el payload; en un PATCH que cambia
     * SÓLO to_path hay que compararlo contra el from_path ya guardado para no crear
     * un bucle (from == to). Evaluamos los valores EFECTIVOS (payload o modelo).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->has('to_path')) {
                return;
            }

            $existing = Redirect::findByUlid((string) $this->route('redirect'));
            $effectiveFrom = $this->input('from_path', $existing?->from_path);

            if (is_string($effectiveFrom) && $this->input('to_path') === $effectiveFrom) {
                $validator->errors()->add('to_path', 'El origen y el destino no pueden ser iguales.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'from_path.regex' => 'La ruta de origen debe empezar por "/" y no puede contener espacios ni protocolo.',
            'to_path.regex' => 'El destino debe ser una ruta interna que empiece por "/".',
            'from_path.unique' => 'Ya existe un redirect con esta ruta de origen en el sitio.',
            'to_path.different' => 'El origen y el destino no pueden ser iguales.',
        ];
    }
}
