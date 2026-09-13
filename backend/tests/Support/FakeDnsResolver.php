<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Domains\Application\DnsResolver;

/**
 * DnsResolver de prueba: devuelve los targets de un mapa por hostname, o un default para
 * los no listados. Sin red.
 */
final class FakeDnsResolver implements DnsResolver
{
    /**
     * @param  array<string, list<string>>  $map  hostname => targets
     * @param  list<string>  $default  targets para hostnames no mapeados
     */
    public function __construct(
        private readonly array $map = [],
        private readonly array $default = [],
    ) {}

    public function targetsFor(string $hostname): array
    {
        return $this->map[$hostname] ?? $this->default;
    }
}
