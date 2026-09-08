<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Rendering;

/**
 * Resuelve los placeholders de binding de un page schema (ADR-012). Recorre el árbol
 * decodificado y sustituye cada nodo que sea EXACTAMENTE `{ "$bind": "<ruta>" }` por
 * el valor del mapa allow-set. Rutas no permitidas → null (nunca acceso arbitrario:
 * sin eval, sin reflexión). El resto de nodos pasan sin tocar.
 */
final class BindingResolver
{
    /**
     * @param  array<string, mixed>  $map  Rutas permitidas → valor (allow-set).
     */
    public static function resolve(mixed $node, array $map): mixed
    {
        if ($node instanceof \stdClass) {
            $vars = get_object_vars($node);

            // Nodo binding: exactamente una clave `$bind` de tipo string.
            if (count($vars) === 1 && isset($vars['$bind']) && is_string($vars['$bind'])) {
                return $map[$vars['$bind']] ?? null;
            }

            $out = new \stdClass;
            foreach ($vars as $key => $value) {
                $out->{$key} = self::resolve($value, $map);
            }

            return $out;
        }

        if (is_array($node)) {
            return array_map(fn ($value) => self::resolve($value, $map), $node);
        }

        return $node;
    }
}
