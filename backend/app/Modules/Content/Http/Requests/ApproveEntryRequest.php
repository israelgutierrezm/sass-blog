<?php

declare(strict_types=1);

namespace App\Modules\Content\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Aprobación editorial de una entrada (ADR-023). `publish_at` opcional: nula/pasada = publicar
 * ya; futura = programar. La Policy (`entry.publish`) autoriza en el controlador.
 */
final class ApproveEntryRequest extends FormRequest
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
            'publish_at' => ['nullable', 'date'],
        ];
    }
}
