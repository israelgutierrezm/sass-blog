<?php

declare(strict_types=1);

namespace App\Modules\Content\Http\Rules;

use App\Modules\Content\Application\Validation\EntryDataValidator;
use App\Modules\Content\Infrastructure\Models\Collection;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Regla de Form Request: el `data` de la entry debe validar contra el schema de
 * su colección. Delega en EntryDataValidator (perfil draft/publish). El primer
 * error se reporta con su ruta de campo.
 */
final class ValidEntryData implements ValidationRule
{
    public function __construct(
        private readonly Collection $collection,
        private readonly string $profile = 'draft',
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            $fail('El contenido debe ser un objeto de campos.');

            return;
        }

        $errors = app(EntryDataValidator::class)->validate($this->collection->fields, $value, $this->profile);

        if ($errors !== []) {
            $first = $errors[0];
            $where = $first['path'] !== '' ? " ({$first['path']})" : '';
            $fail("Contenido inválido{$where}: {$first['message']}");
        }
    }
}
