<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Tenancy\Exceptions;

use RuntimeException;

/**
 * Se lanza al intentar cambiar el `workspace_id` de una fila ya existente.
 *
 * El scope protege las lecturas; esto protege las escrituras: sin ello, un
 * update podría trasladar un recurso al workspace de otro y el scope ni se
 * enteraría porque la fila ya estaría del otro lado.
 */
final class WorkspaceMismatchException extends RuntimeException
{
    public static function cannotChangeWorkspace(string $model, ?int $from, ?int $to): self
    {
        return new self(sprintf(
            'No se permite cambiar el workspace de %s (de %s a %s). El aislamiento de '
            .'tenant es inmutable a nivel de fila.',
            $model,
            $from === null ? 'null' : (string) $from,
            $to === null ? 'null' : (string) $to,
        ));
    }
}
