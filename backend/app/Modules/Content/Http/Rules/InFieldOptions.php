<?php

declare(strict_types=1);

namespace App\Modules\Content\Http\Rules;

use App\Modules\Content\Infrastructure\Models\CollectionField;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * El valor debe ser una de las opciones declaradas en config.options del campo
 * (select/multiselect). Las opciones pueden ser strings o {value,label}.
 */
final class InFieldOptions implements ValidationRule
{
    public function __construct(private readonly CollectionField $field) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! in_array($value, $this->allowedValues(), true)) {
            $fail('El valor no es una de las opciones permitidas.');
        }
    }

    /** @return list<mixed> */
    private function allowedValues(): array
    {
        $options = (array) (($this->field->config['options'] ?? []));

        return array_values(array_map(
            fn ($option) => is_array($option) ? ($option['value'] ?? null) : $option,
            $options,
        ));
    }
}
