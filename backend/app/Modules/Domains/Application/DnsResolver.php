<?php

declare(strict_types=1);

namespace App\Modules\Domains\Application;

/**
 * Resuelve los destinos DNS (CNAME/A) de un hostname (ADR-020). Abstracción inyectable:
 * la impl real consulta el DNS del sistema; los tests inyectan un fake. Así la verificación
 * de dominios no depende de la red.
 */
interface DnsResolver
{
    /**
     * @return list<string> destinos a los que resuelve el hostname (targets CNAME + IPs A)
     */
    public function targetsFor(string $hostname): array;
}
