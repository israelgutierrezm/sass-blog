<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Capabilities;

/**
 * Contrato del kernel para resolver las capabilities de un workspace.
 *
 * El kernel NO sabe de planes ni suscripciones: eso es del módulo Billing, que
 * implementa este contrato y lo enlaza en su ServiceProvider. Así el kernel
 * razona sobre capabilities sin depender de un módulo de dominio (patrón de sonda).
 */
interface CapabilityResolver
{
    /**
     * Capabilities otorgadas al workspace, como mapa key => límite (null = sin límite).
     *
     * @return array<string, int|null>
     */
    public function grantsFor(int $workspaceId): array;
}
