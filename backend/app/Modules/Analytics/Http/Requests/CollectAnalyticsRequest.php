<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Entrada de la ingesta pública de analítica (ADR-021). El `site` es el ULID público (26).
 * `path`/`referrer` se normalizan en el servicio; aquí sólo se acota su forma. La IP y el
 * User-Agent del visitante llegan por cabeceras (X-Visitor-Ip / User-Agent), no en el body.
 */
final class CollectAnalyticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // superficie pública (ADR-006); el sitio se resuelve en el servidor
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'site' => ['required', 'string', 'size:26'],
            'path' => ['required', 'string', 'max:2048'],
            'referrer' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
