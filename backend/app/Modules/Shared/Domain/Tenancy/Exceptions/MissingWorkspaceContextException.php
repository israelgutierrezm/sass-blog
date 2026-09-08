<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Tenancy\Exceptions;

use RuntimeException;

/**
 * Se lanza cuando el dominio intenta operar sin un workspace resuelto.
 *
 * Es deliberado que falle ruidosamente en vez de devolver vacío: un scope que
 * devuelve cero filas cuando falta contexto disfraza un error de programación
 * como un resultado legítimo.
 */
final class MissingWorkspaceContextException extends RuntimeException
{
    public static function make(): self
    {
        return new self(
            'No hay workspace activo en el contexto. El código de dominio exige un '
            .'workspace resuelto (middleware en HTTP, WorkspaceContext::runFor fuera de HTTP).'
        );
    }
}
