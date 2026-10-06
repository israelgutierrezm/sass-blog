<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Domains\Application\DnsResolver;

/**
 * DnsResolver de prueba, sin red: targets CNAME/A de un mapa por hostname (o un default para los
 * no listados) y registros TXT que el test «publica» como haría el tenant en su DNS.
 */
final class FakeDnsResolver implements DnsResolver
{
    /** @var array<string, list<string>> nombre => valores TXT */
    private array $txt = [];

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

    public function hasTxtRecord(string $name, string $expected): bool
    {
        return in_array($expected, $this->txt[$name] ?? [], true);
    }

    /** Publica un TXT (lo que hace el dueño del dominio en su panel DNS). */
    public function publishTxt(string $name, string $value): self
    {
        $this->txt[$name][] = $value;

        return $this;
    }
}
