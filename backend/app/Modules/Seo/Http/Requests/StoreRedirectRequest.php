<?php

declare(strict_types=1);

namespace App\Modules\Seo\Http\Requests;

use App\Modules\Shared\Domain\Tenancy\WorkspaceContext;
use App\Modules\Sites\Infrastructure\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreRedirectRequest extends FormRequest
{
    /**
     * Ruta de sitio segura: UN solo "/" inicial (no "//" ni "/\", que los navegadores
     * tratan como URL protocol-relative → open redirect), sin espacios, query ni
     * fragmento (el match del render es sólo sobre el path) ni barra invertida.
     */
    public const PATH_REGEX = '/^\/(?![\/\\\\])[^\s?#\\\\]*$/';

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
            'from_path' => [
                'required', 'string', 'max:2048', 'regex:'.self::PATH_REGEX,
                Rule::unique('redirects', 'from_path')
                    ->where('workspace_id', $workspaceId)
                    ->where('site_id', $siteId),
            ],
            // `different` evita el bucle from == to (ambos ya normalizados aquí).
            'to_path' => ['required', 'string', 'max:2048', 'regex:'.self::PATH_REGEX, 'different:from_path'],
            'status' => ['sometimes', Rule::in([301, 302])],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(self::normalizePaths($this));
    }

    /**
     * Normaliza from_path/to_path como el render (PublicPageController::normalizePath):
     * "/" inicial garantizado y sin "/" final (salvo raíz), para que el `from_path`
     * guardado case EXACTO con la clave de búsqueda del render.
     *
     * @return array<string, string>
     */
    public static function normalizePaths(FormRequest $request): array
    {
        $data = [];
        foreach (['from_path', 'to_path'] as $key) {
            $value = $request->input($key);
            if (is_string($value)) {
                $data[$key] = self::normalizePath($value);
            }
        }

        return $data;
    }

    public static function normalizePath(string $path): string
    {
        $path = '/'.ltrim(trim($path), '/');

        return $path === '/' ? '/' : rtrim($path, '/');
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
