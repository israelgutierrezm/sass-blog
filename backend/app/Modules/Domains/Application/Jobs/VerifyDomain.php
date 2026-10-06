<?php

declare(strict_types=1);

namespace App\Modules\Domains\Application\Jobs;

use App\Modules\Domains\Application\DnsResolver;
use App\Modules\Domains\Infrastructure\Models\SiteDomain;
use App\Modules\Shared\Domain\Tenancy\WorkspaceContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Verifica una reclamación de dominio (ADR-025, reemplaza la verificación por apuntado de
 * ADR-020). Activa sólo si:
 *
 * 1. PROPIEDAD: existe el TXT `_sassblog-verify.{hostname}` con el token de ESTA reclamación
 *    (sólo quien controla la zona DNS puede publicarlo; el apuntado solo no lo prueba).
 * 2. ENRUTADO: el hostname apunta (CNAME/A) a nuestro ingress.
 * 3. Ningún otro sitio lo tiene ya activo (UNIQUE sobre `active_hostname`).
 *
 * Si algo falla queda `failed` con `failure_reason`. Corre en la cola `database` dentro del
 * WorkspaceContext del tenant. MVP: intento único; el reintento con backoff mientras propaga el
 * DNS es evolución declarada.
 */
final class VerifyDomain implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $domainId,
        public readonly int $workspaceId,
    ) {}

    public function handle(WorkspaceContext $context, DnsResolver $dns): void
    {
        $context->runFor($this->workspaceId, function () use ($dns): void {
            $domain = SiteDomain::find($this->domainId);
            if ($domain === null || $domain->isActive()) {
                return;
            }

            $domain->update(['status' => SiteDomain::STATUS_VERIFYING, 'failure_reason' => null]);

            if (! $dns->hasTxtRecord($domain->challengeName(), $domain->challengeValue())) {
                $this->markFailed($domain, SiteDomain::FAILURE_OWNERSHIP);

                return;
            }

            if (! $this->pointsToIngress($dns, $domain->hostname)) {
                $this->markFailed($domain, SiteDomain::FAILURE_ROUTING);

                return;
            }

            try {
                $domain->update(['status' => SiteDomain::STATUS_ACTIVE, 'verified_at' => now()]);
            } catch (UniqueConstraintViolationException) {
                // Otro sitio ya lo tiene activo: no se le quita. Se descarta el intento fallido.
                $domain->refresh();
                $this->markFailed($domain, SiteDomain::FAILURE_TAKEN);
            }
        });
    }

    private function pointsToIngress(DnsResolver $dns, string $hostname): bool
    {
        $targets = array_map('strtolower', $dns->targetsFor($hostname));
        $expected = array_map('strtolower', array_filter([
            (string) config('sassblog.domains.ingress_cname'),
            (string) config('sassblog.domains.ingress_ip'),
        ]));

        return array_intersect($targets, $expected) !== [];
    }

    private function markFailed(SiteDomain $domain, string $reason): void
    {
        $domain->update(['status' => SiteDomain::STATUS_FAILED, 'failure_reason' => $reason]);
    }
}
