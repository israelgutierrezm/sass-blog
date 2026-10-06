<?php

declare(strict_types=1);

namespace App\Modules\Domains\Application;

/**
 * Consultas DNS de la verificación de dominios (ADR-020 + ADR-025). Abstracción inyectable:
 * la impl real consulta el DNS del sistema; los tests inyectan un fake. Así la verificación
 * de dominios no depende de la red.
 */
interface DnsResolver
{
    /**
     * @return list<string> destinos a los que resuelve el hostname (targets CNAME + IPs A)
     */
    public function targetsFor(string $hostname): array;

    /** ¿Existe en `$name` un registro TXT con exactamente el valor `$expected`? (propiedad) */
    public function hasTxtRecord(string $name, string $expected): bool;
}
