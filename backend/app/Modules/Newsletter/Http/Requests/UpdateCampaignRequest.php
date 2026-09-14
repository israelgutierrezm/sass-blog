<?php

declare(strict_types=1);

namespace App\Modules\Newsletter\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Edición de una campaña en borrador (ADR-022). La Policy autoriza; el controlador rechaza
 * editar una campaña ya enviada.
 */
final class UpdateCampaignRequest extends FormRequest
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
            'subject' => ['sometimes', 'required', 'string', 'max:255'],
            'body' => ['sometimes', 'required', 'string', 'max:65535'],
        ];
    }
}
