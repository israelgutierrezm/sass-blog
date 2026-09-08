<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Support\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Identificador público ULID para entidades expuestas por API.
 *
 * Regla del proyecto: la PK autoincremental es interna y NUNCA se expone al
 * cliente. Un id secuencial visible filtra volumen de negocio y permite enumerar
 * recursos ajenos sumando uno. El ULID es la llave de ruta de la API.
 *
 * `Str::ulid()` genera base32 de Crockford en MAYÚSCULAS; todo valor recibido del
 * cliente se normaliza a mayúsculas antes de resolver, para que un ULID en
 * minúsculas en la URL encuentre la misma fila.
 */
trait HasPublicUlid
{
    public static function bootHasPublicUlid(): void
    {
        static::creating(function (Model $model): void {
            if (blank($model->getAttribute('ulid'))) {
                $model->setAttribute('ulid', self::newUlid());
            }
        });
    }

    public static function newUlid(): string
    {
        return Str::upper((string) Str::ulid());
    }

    /**
     * El ULID es la llave de ruta: las URLs de la API exponen el id público.
     */
    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * Normaliza el valor recibido antes de resolver el binding de ruta.
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $field ??= $this->getRouteKeyName();

        if ($field === 'ulid') {
            $value = Str::upper((string) $value);
        }

        return $query->where($this->qualifyColumn($field), $value);
    }

    public static function findByUlid(string $ulid): ?static
    {
        return static::query()
            ->where('ulid', Str::upper($ulid))
            ->first();
    }
}
