<?php

declare(strict_types=1);

namespace App\Modules\Content\Http\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * URL con esquema http/https (bloquea javascript:, data:, etc.). Se aplica a los
 * campos url y media, que se renderizan como href/src (ADR-010).
 */
final class SafeUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('Debe ser una URL válida.');

            return;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            $fail('La URL debe usar http o https.');
        }
    }
}
