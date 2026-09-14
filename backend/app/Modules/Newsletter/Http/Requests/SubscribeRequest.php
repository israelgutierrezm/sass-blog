<?php

declare(strict_types=1);

namespace App\Modules\Newsletter\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta pública a la newsletter (ADR-022). El `email` es dato propio del visitante (sí llega del
 * cliente); el sitio se resuelve en el servidor por el ULID de la ruta. Se normaliza a minúsculas
 * para que la unicidad por sitio sea insensible a mayúsculas.
 */
final class SubscribeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');
        if (is_string($email)) {
            $this->merge(['email' => strtolower(trim($email))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:255'],
        ];
    }
}
