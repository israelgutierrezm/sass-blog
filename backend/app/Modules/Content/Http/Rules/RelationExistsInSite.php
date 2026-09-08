<?php

declare(strict_types=1);

namespace App\Modules\Content\Http\Rules;

use App\Modules\Content\Infrastructure\Models\CollectionField;
use App\Modules\Content\Infrastructure\Models\Entry;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

/**
 * El valor (ULID) debe referir a una entry existente de la colección relacionada Y
 * del MISMO site (ADR-010). La consulta corre bajo el WorkspaceScope activo, así
 * que además garantiza el mismo workspace.
 */
final class RelationExistsInSite implements ValidationRule
{
    public function __construct(private readonly CollectionField $field) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->field->related_collection_id === null) {
            $fail('El campo de relación no está configurado.');

            return;
        }

        if (! is_string($value)) {
            $fail('Referencia inválida.');

            return;
        }

        $exists = Entry::query()
            ->where('ulid', Str::upper($value))
            ->where('collection_id', $this->field->related_collection_id)
            ->where('site_id', $this->field->site_id)
            ->exists();

        if (! $exists) {
            $fail('La entrada relacionada no existe en este sitio.');
        }
    }
}
