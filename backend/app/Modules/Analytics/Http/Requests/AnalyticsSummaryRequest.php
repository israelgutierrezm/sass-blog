<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Rango del dashboard de analítica (ADR-021). `from`/`to` opcionales; el gating por plan y el
 * recorte de rango (plan básico) los aplica el controlador. La Policy autoriza en el controlador.
 */
final class AnalyticsSummaryRequest extends FormRequest
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
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ];
    }
}
