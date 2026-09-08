<?php

declare(strict_types=1);

namespace App\Modules\Builder\Application\Schema;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use stdClass;

/**
 * Valida un page schema contra el JSON Schema generado (ADR-004), por perfil
 * (draft relajado / publish estricto), más la regla semántica de ids de sección
 * únicos (que el JSON Schema no expresa).
 *
 * IMPORTANTE: valida sobre valores JSON DECODIFICADOS COMO OBJETOS (stdClass), no
 * arrays asociativos: PHP no distingue `{}` de `[]`, así que un settings/props
 * vacío re-codificado desde un array se volvería `[]` y opis lo rechazaría como
 * "no es objeto". Por eso las entradas son JSON crudo (validateJson) o valores ya
 * decodificados con json_decode(..., false).
 */
final class PageSchemaValidator
{
    public function __construct(private readonly SchemaRepository $schemas) {}

    /**
     * @return list<array{path: string, message: string}>
     */
    public function validateJson(string $json, string $profile = 'draft'): array
    {
        return $this->validate(json_decode($json), $profile);
    }

    /**
     * @param  mixed  $data  Valor decodificado como OBJETOS (stdClass), no assoc.
     * @return list<array{path: string, message: string}>
     */
    public function validate(mixed $data, string $profile = 'draft'): array
    {
        $errors = [];

        $result = (new Validator)->validate($data, $this->schemas->schema($profile));

        if (! $result->isValid() && $result->error() !== null) {
            $keyed = (new ErrorFormatter)->formatKeyed($result->error());
            foreach ($keyed as $pointer => $messages) {
                foreach ((array) $messages as $message) {
                    $errors[] = ['path' => (string) $pointer, 'message' => (string) $message];
                }
            }
        }

        foreach ($this->duplicateSectionIds($data) as $id) {
            $errors[] = ['path' => 'sections', 'message' => "id de sección duplicado: {$id}"];
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function duplicateSectionIds(mixed $data): array
    {
        $sections = $data instanceof stdClass && isset($data->sections) && is_array($data->sections)
            ? $data->sections
            : [];

        $seen = [];
        $dupes = [];
        foreach ($sections as $section) {
            $id = $section instanceof stdClass ? ($section->id ?? null) : null;
            if (! is_string($id)) {
                continue;
            }
            if (isset($seen[$id])) {
                $dupes[$id] = true;
            }
            $seen[$id] = true;
        }

        return array_keys($dupes);
    }
}
