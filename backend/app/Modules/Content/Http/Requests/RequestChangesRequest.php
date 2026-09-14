<?php

declare(strict_types=1);

namespace App\Modules\Content\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * "Pedir cambios" sobre una entrada en revisión (ADR-023): devuelve a borrador con una nota
 * para el redactor. La Policy (`entry.publish`) autoriza en el controlador.
 */
final class RequestChangesRequest extends FormRequest
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
        return [
            'note' => ['required', 'string', 'max:2000'],
        ];
    }
}
