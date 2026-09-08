<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Capabilities;

use App\Modules\Shared\Domain\Capabilities\Exceptions\CapabilityDeniedException;
use App\Modules\Shared\Domain\Tenancy\WorkspaceContext;

/**
 * Servicio central de capabilities. El ÚNICO lugar donde se pregunta "¿el plan
 * permite X?". Prohibido `if ($plan === 'pro')` regado por el código.
 *
 * Resuelve siempre para el workspace activo del contexto. Cachea las concesiones
 * por workspace durante el request para no repetir la consulta.
 */
final class Capabilities
{
    /** @var array<int, array<string, int|null>> */
    private array $cache = [];

    public function __construct(
        private readonly WorkspaceContext $context,
        private readonly CapabilityResolver $resolver,
    ) {}

    public function allows(Capability|string $capability): bool
    {
        return array_key_exists($this->key($capability), $this->grants());
    }

    /**
     * Límite (cuota) de una capability otorgada, o null si es ilimitada o no se otorga.
     */
    public function limit(Capability|string $capability): ?int
    {
        return $this->grants()[$this->key($capability)] ?? null;
    }

    /**
     * @throws CapabilityDeniedException si el plan no otorga la capability
     */
    public function authorize(Capability|string $capability): void
    {
        if (! $this->allows($capability)) {
            throw CapabilityDeniedException::for($this->key($capability));
        }
    }

    /**
     * @return array<string, int|null>
     */
    private function grants(): array
    {
        $workspaceId = $this->context->id();

        return $this->cache[$workspaceId] ??= $this->resolver->grantsFor($workspaceId);
    }

    private function key(Capability|string $capability): string
    {
        return $capability instanceof Capability ? $capability->value : $capability;
    }
}
