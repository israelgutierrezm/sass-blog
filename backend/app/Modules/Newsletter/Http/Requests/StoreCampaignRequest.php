<?php

declare(strict_types=1);

namespace App\Modules\Newsletter\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta de una campaña (ADR-022). La Policy (`newsletter.manage`) autoriza en el controlador.
 */
final class StoreCampaignRequest extends FormRequest
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
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:65535'],
        ];
    }
}
