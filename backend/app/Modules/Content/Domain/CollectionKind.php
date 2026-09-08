<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain;

/**
 * Tipo de colección (catálogo cerrado). `article` es el preset editorial; el resto
 * son colecciones genéricas definidas por el usuario.
 */
enum CollectionKind: string
{
    case Generic = 'generic';
    case Article = 'article';

    public function label(): string
    {
        return match ($this) {
            self::Generic => 'Genérica',
            self::Article => 'Artículos',
        };
    }
}
