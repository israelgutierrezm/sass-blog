<?php

declare(strict_types=1);

namespace App\Modules\Domains\Application\Jobs;

use App\Modules\Domains\Application\DnsResolver;
use App\Modules\Domains\Infrastructure\Models\SiteDomain;
use App\Modules\Shared\Domain\Tenancy\WorkspaceContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Verifica un dominio por apuntado DNS (ADR-020): resuelve el hostname y comprueba que
 * apunta a nuestro ingress (CNAME o IP). Coincide ⇒ `active`; si no ⇒ `failed`. Corre en la
 * cola `database`; fija el WorkspaceContext desde el id del workspace.
 *
 * MVP: intento único (active|failed). El reintento con backoff hasta que propague el DNS es
 * evolución declarada.
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

            $domain->update(['status' => SiteDomain::STATUS_VERIFYING]);

            $targets = array_map('strtolower', $dns->targetsFor($domain->hostname));
            $expected = array_map('strtolower', array_filter([
                (string) config('sassblog.domains.ingress_cname'),
                (string) config('sassblog.domains.ingress_ip'),
            ]));

            $pointsToUs = array_intersect($targets, $expected) !== [];

            $domain->update($pointsToUs
                ? ['status' => SiteDomain::STATUS_ACTIVE, 'verified_at' => now()]
                : ['status' => SiteDomain::STATUS_FAILED]);
        });
    }
}
