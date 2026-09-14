<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Domain;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Deriva el `visitor_hash` de un pageview (ADR-021), SIN almacenar PII.
 *
 * `HMAC(mensaje = IP|UA, clave = APP_KEY|site|día)`. La clave incluye el día y el sitio, así
 * que el mismo visitante:
 *   - rinde el mismo hash dentro de un (sitio, día) → permite contar únicos diarios;
 *   - rinde uno DISTINTO al día siguiente o en otro sitio → no hay seguimiento entre días ni
 *     correlación entre sitios.
 * El resultado es irreversible: de él no se recupera la IP. No se guarda la IP en ninguna parte.
 */
final class VisitorHasher
{
    public function hash(int $siteId, string $ip, string $userAgent, ?DateTimeInterface $day = null): string
    {
        $dayKey = ($day ? Carbon::instance($day) : Carbon::now())->format('Y-m-d');
        $key = (string) config('app.key').'|'.$siteId.'|'.$dayKey;

        return hash_hmac('sha256', $ip.'|'.$userAgent, $key);
    }
}
