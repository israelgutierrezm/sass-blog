<?php

declare(strict_types=1);

namespace App\Modules\Domains\Infrastructure;

use App\Modules\Domains\Application\DnsResolver;

/**
 * DnsResolver de producción (ADR-020): consulta el DNS del sistema (CNAME + A). Devuelve
 * los targets normalizados (minúsculas, sin punto final) para comparar con el ingress.
 */
final class SystemDnsResolver implements DnsResolver
{
    public function targetsFor(string $hostname): array
    {
        $records = @dns_get_record($hostname, DNS_CNAME | DNS_A) ?: [];
        $targets = [];

        foreach ($records as $record) {
            if (isset($record['target']) && is_string($record['target'])) {
                $targets[] = rtrim(strtolower($record['target']), '.');
            }
            if (isset($record['ip']) && is_string($record['ip'])) {
                $targets[] = $record['ip'];
            }
        }

        return array_values(array_unique($targets));
    }
}
