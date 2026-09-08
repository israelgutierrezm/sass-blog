<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Validation;

use App\Modules\Content\Domain\Fields\FieldType;
use App\Modules\Content\Http\Rules\InFieldOptions;
use App\Modules\Content\Http\Rules\RelationExistsInSite;
use App\Modules\Content\Http\Rules\SafeUrl;
use App\Modules\Content\Infrastructure\Models\CollectionField;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Validator;

/**
 * Valida el `data` de una entry contra el schema de su colección en runtime
 * (ADR-009/010). Ensambla un Validator de Laravel a partir de collection_fields:
 * las reglas por tipo salen del catálogo cerrado FieldType. Dos perfiles:
 *   - draft:   todo opcional (guardado incremental).
 *   - publish: los campos required deben estar presentes y no vacíos.
 * Las claves no declaradas por el schema se rechazan (sin campos fantasma).
 *
 * Clave de diseño: el validador es AGNÓSTICO a la key. Cada campo se mapea a un
 * atributo sintético (f0, f1, …) y su valor se toma con acceso LITERAL a la clave.
 * Así un punto o un comodín en la key (p. ej. "seo.canonical" o "*") NUNCA se
 * reinterpreta como notación de ruta anidada de Laravel —que evadiría las reglas—.
 * Las rutas de error se traducen de vuelta a la key real.
 */
final class EntryDataValidator
{
    /**
     * @param  iterable<CollectionField>  $fields
     * @param  array<array-key, mixed>  $data
     * @return list<array{path: string, message: string}>
     */
    public function validate(iterable $fields, array $data, string $profile = 'draft'): array
    {
        /** @var SupportCollection<int, CollectionField> $fields */
        $fields = collect($fields)->values();

        $payload = [];
        $rules = [];
        $names = [];
        $keyByAttribute = [];

        foreach ($fields as $index => $field) {
            $attribute = 'f'.$index;
            $keyByAttribute[$attribute] = (string) $field->key;
            $names[$attribute] = $field->label;

            // Acceso literal: no dejamos que la key entre en la notación de puntos.
            // La cadena vacía "" se comporta como ausente para reglas no-implícitas
            // (gate presentOrRuleIsImplicit de Laravel), así que no la normalizamos.
            $payload[$attribute] = array_key_exists($field->key, $data) ? $data[$field->key] : null;

            [$main, $item] = $this->rulesFor($field, $profile);
            $rules[$attribute] = $main;
            if ($item !== null) {
                $rules[$attribute.'.*'] = $item;
                $names[$attribute.'.*'] = $field->label;
            }
        }

        $validator = Validator::make($payload, $rules, [], $names);

        $errors = [];
        foreach ($validator->errors()->messages() as $attribute => $messages) {
            [$base, $suffix] = array_pad(explode('.', (string) $attribute, 2), 2, null);
            $realKey = $keyByAttribute[$base] ?? $base;
            $path = $suffix === null ? $realKey : $realKey.'.'.$suffix;
            foreach ($messages as $message) {
                $errors[] = ['path' => $path, 'message' => $message];
            }
        }

        // Claves no declaradas por la colección: sin campos fantasma. Comparación por
        // string porque PHP normaliza las claves numéricas del JSON a int.
        $declared = $fields->map(fn (CollectionField $f) => (string) $f->key)->all();
        foreach (array_keys($data) as $key) {
            if (! in_array((string) $key, $declared, true)) {
                $errors[] = ['path' => (string) $key, 'message' => "El campo «{$key}» no está declarado en la colección."];
            }
        }

        return $errors;
    }

    /**
     * Reglas para un campo: [reglas del valor, reglas de cada elemento|null].
     * El segundo elemento aplica a colecciones (multiselect, media_array) vía
     * la clave "{attr}.*".
     *
     * @return array{0: list<mixed>, 1: list<mixed>|null}
     */
    private function rulesFor(CollectionField $field, string $profile): array
    {
        $presence = ($profile === 'publish' && $field->required) ? 'required' : 'nullable';

        $main = match ($field->type) {
            FieldType::Text, FieldType::Textarea, FieldType::RichText, FieldType::Slug => [$presence, 'string'],
            FieldType::Email => [$presence, 'string', 'email:rfc'],
            FieldType::Url => [$presence, 'string', new SafeUrl],
            FieldType::Integer => [$presence, 'integer'],
            FieldType::Decimal => [$presence, 'numeric'],
            FieldType::Money => [$presence, 'numeric', 'decimal:0,2'],
            FieldType::Boolean => [$presence, 'boolean'],
            FieldType::Date => [$presence, 'date_format:Y-m-d'],
            FieldType::DateTime => [$presence, 'date_format:Y-m-d\TH:i:sP,Y-m-d\TH:i:s,Y-m-d H:i:s'],
            FieldType::Select => [$presence, 'string', new InFieldOptions($field)],
            FieldType::MultiSelect => [$presence, 'array'],
            FieldType::Media => [$presence, 'string', new SafeUrl],
            FieldType::MediaArray => [$presence, 'array'],
            FieldType::Relation => [$presence, 'string', new RelationExistsInSite($field)],
            FieldType::Json => [$presence, 'array'],
        };

        $item = match ($field->type) {
            FieldType::MultiSelect => ['string', new InFieldOptions($field)],
            FieldType::MediaArray => ['string', new SafeUrl],
            default => null,
        };

        return [$main, $item];
    }
}
