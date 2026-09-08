<?php

declare(strict_types=1);

namespace App\Modules\Builder\Http\Rules;

use App\Modules\Builder\Application\Schema\PageSchemaValidator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Regla de Form Request: el page schema es válido contra el registry (ADR-004),
 * en el perfil dado. Valida el JSON CRUDO del cuerpo (objetos preservados), no el
 * array asociativo que Laravel decodifica (donde {} se volvería []).
 */
final class ValidPageSchema implements ValidationRule
{
    public function __construct(private readonly string $profile = 'draft') {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            $fail('El contenido de la página debe ser un objeto con schema_version y sections.');

            return;
        }

        $body = json_decode((string) request()->getContent());
        $data = (is_object($body) || is_array($body)) ? data_get($body, $attribute) : null;

        // Fallback si el cuerpo no es JSON parseable: se pierde la distinción {}/[]
        // de objetos vacíos, pero cubre el caso general.
        $data ??= json_decode((string) json_encode($value));

        $errors = app(PageSchemaValidator::class)->validate($data, $this->profile);

        if ($errors !== []) {
            $first = $errors[0];
            $where = ($first['path'] !== '' && $first['path'] !== '/') ? " ({$first['path']})" : '';
            $fail("El contenido de la página no es válido: {$first['message']}{$where}");
        }
    }
}
