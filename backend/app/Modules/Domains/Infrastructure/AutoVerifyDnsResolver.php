<?php

declare(strict_types=1);

namespace App\Modules\Domains\Infrastructure;

use App\Modules\Domains\Application\DnsResolver;

/**
 * DnsResolver de NO producción (ADR-020): devuelve el destino del ingress para cualquier
 * hostname, de modo que la verificación queda en verde sin DNS real. Se enlaza sólo cuando
 * `sassblog.domains.auto_verify` es true (E2E/staging); en prod se usa SystemDnsResolver.
 */
final class AutoVerifyDnsResolver implements DnsResolver
{
    public function targetsFor(string $hostname): array
    {
        return array_values(array_filter([
            (string) config('sassblog.domains.ingress_cname'),
            (string) config('sassblog.domains.ingress_ip'),
        ]));
    }
}
