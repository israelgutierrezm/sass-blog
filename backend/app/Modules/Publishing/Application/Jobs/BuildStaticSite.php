<?php

declare(strict_types=1);

namespace App\Modules\Publishing\Application\Jobs;

use App\Modules\Publishing\Application\StaticSiteBuilder;
use App\Modules\Publishing\Infrastructure\Models\Deployment;
use App\Modules\Shared\Domain\Tenancy\WorkspaceContext;
use App\Modules\Sites\Infrastructure\Models\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Build estático de un sitio (ADR-019). Mueve el Deployment por sus estados y ensambla el
 * artefacto (ZIP) con StaticSiteBuilder. Corre en la cola `database`; fija el
 * WorkspaceContext desde el id del workspace.
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
                $site = Site::find($deployment->site_id);
                if ($site === null) {
                    throw new \RuntimeException('Sitio no encontrado para el deployment.');
                }

                $result = app(StaticSiteBuilder::class)->build($site, $deployment->ulid);

                $deployment->update([
                    'status' => Deployment::STATUS_SUCCESS,
                    'artifact_ref' => $result['artifact_ref'],
                    'bytes' => $result['bytes'],
                ]);
            } catch (Throwable $e) {
                $deployment->update([
                    'status' => Deployment::STATUS_FAILED,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }
}
