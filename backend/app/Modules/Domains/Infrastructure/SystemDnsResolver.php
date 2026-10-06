<?php

declare(strict_types=1);

namespace App\Modules\Domains\Infrastructure;

use App\Modules\Domains\Application\DnsResolver;

/**
 * DnsResolver de producción (ADR-020 + ADR-025): consulta el DNS del sistema. Targets CNAME + A
 * normalizados (minúsculas, sin punto final) para comparar con el ingress, y registros TXT para
 * la prueba de propiedad.
 */
final class SystemDnsResolver implements DnsResolver
{
    public function hasTxtRecord(string $name, string $expected): bool
    {
        $records = @dns_get_record($name, DNS_TXT) ?: [];

        foreach ($records as $record) {
            // Un TXT puede venir partido en varias cadenas: `txt` ya las trae concatenadas.
            $value = $record['txt'] ?? (isset($record['entries']) && is_array($record['entries']) ? implode('', $record['entries']) : null);

            // Algunos paneles DNS guardan las comillas que el usuario pega: se ignoran.
            if (is_string($value) && trim($value, " \t\"") === $expected) {
                return true;
            }
        }

        return false;
    }

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
