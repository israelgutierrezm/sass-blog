<?php

declare(strict_types=1);

namespace App\Modules\Publishing\Application\Jobs;

use App\Modules\Publishing\Infrastructure\Models\Deployment;
use App\Modules\Shared\Domain\Tenancy\WorkspaceContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Build estático de un sitio (ADR-019). Mueve el Deployment por sus estados y, al final,
 * deja el artefacto (ZIP) referenciado. Corre en la cola `database`; fija el
 * WorkspaceContext desde el id del workspace.
 *
 * ANDAMIO (sub-slice 5.1): aún NO genera el artefacto — sólo avanza pending→building→
 * success. La enumeración + manifest (5.2), el render Node (5.3) y el ensamblado/ZIP
 * (5.4) se materializan en sus sub-slices. Deuda declarada.
 */
final class BuildStaticSite implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $deploymentId,
        public readonly int $workspaceId,
    ) {}

    public function handle(WorkspaceContext $context): void
    {
        $context->runFor($this->workspaceId, function (): void {
            $deployment = Deployment::find($this->deploymentId);
            if ($deployment === null || $deployment->isTerminal()) {
                return; // ya resuelto: no reprocesar
            }

            $deployment->update(['status' => Deployment::STATUS_BUILDING]);

            try {
                // TODO(5.2-5.4): enumerar URLs + manifest + render Node + ZIP + artifact_ref.
                $deployment->update(['status' => Deployment::STATUS_SUCCESS]);
            } catch (Throwable $e) {
                $deployment->update([
                    'status' => Deployment::STATUS_FAILED,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }
}
