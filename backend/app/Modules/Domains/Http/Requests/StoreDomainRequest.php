<?php

declare(strict_types=1);

namespace App\Modules\Domains\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreDomainRequest extends FormRequest
{
    /** Hostname válido: etiquetas alfanum/guión + TLD (al menos dominio.tld). */
    public const HOSTNAME_REGEX = '/^(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/';

    public function authorize(): bool
    {
        return true; // la Policy decide en el controlador
    }

    protected function prepareForValidation(): void
    {
        $hostname = $this->input('hostname');
        if (is_string($hostname)) {
            $hostname = strtolower(trim($hostname));
            $hostname = (string) preg_replace('#^https?://#', '', $hostname); // sin esquema
            $hostname = explode('/', $hostname)[0];                            // sin path
            $this->merge(['hostname' => rtrim($hostname, '.')]);               // sin punto final
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // `hostname` ÚNICO en toda la plataforma (un dominio → un sitio).
            'hostname' => ['required', 'string', 'max:253', 'regex:'.self::HOSTNAME_REGEX, Rule::unique('site_domains', 'hostname')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'hostname.regex' => 'Introduce un dominio válido (p.ej. blog.acme.com).',
            'hostname.unique' => 'Ese dominio ya está en uso en la plataforma.',
        ];
    }
}
