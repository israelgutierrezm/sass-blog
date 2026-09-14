<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Exceptions;

use RuntimeException;

/**
 * Transición de estado editorial no permitida (ADR-023). La máquina de estados la valida en el
 * dominio (no sólo en la UI); la API la mapea a 422.
 */
final class InvalidEntryTransitionException extends RuntimeException
{
    public static function from(string $current, string $action): self
    {
        return new self("No se puede «{$action}» una entrada en estado «{$current}».");
    }
}
