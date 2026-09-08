<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Capabilities\Exceptions;

use RuntimeException;

/**
 * El plan del workspace no otorga la capability requerida.
 *
 * Distinta de una negación de permiso (RBAC): aquí el usuario podría tener el
 * permiso, pero el PLAN no incluye la funcionalidad.
 */
final class CapabilityDeniedException extends RuntimeException
{
    public static function for(string $capability): self
    {
        return new self("El plan del workspace no incluye la funcionalidad: {$capability}.");
    }
}
