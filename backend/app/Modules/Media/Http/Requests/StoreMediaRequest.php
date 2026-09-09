<?php

declare(strict_types=1);

namespace App\Modules\Media\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreMediaRequest extends FormRequest
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
        /** @var list<string> $mimes */
        $mimes = (array) config('sassblog.media.mimes', []);

        return [
            'file' => [
                'required',
                'file',
                'mimes:'.implode(',', $mimes),
                'max:'.(int) config('sassblog.media.max_kb', 10240),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.mimes' => 'Tipo de archivo no permitido.',
            'file.max' => 'El archivo supera el tamaño máximo permitido.',
        ];
    }
}
