<?php

declare(strict_types=1);

namespace App\Modules\Publishing\Infrastructure;

use App\Modules\Publishing\Application\StaticRenderer;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Render estático de producción (ADR-019): escribe el manifest a un JSON temporal e invoca
 * el CLI Node (`render-static.mjs`, los mismos site-components) por Symfony Process. El CLI
 * emite el HTML por página + assets/styles.css en $outDir. Falla ⇒ excepción (el job la
 * traduce a status=failed).
 */
final class NodeStaticRenderer implements StaticRenderer
{
    public function render(array $manifest, string $outDir): void
    {
        $manifestPath = $outDir.DIRECTORY_SEPARATOR.'manifest.json';
        file_put_contents($manifestPath, (string) json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $process = new Process([
            (string) config('sassblog.publishing.node', 'node'),
            (string) config('sassblog.publishing.cli'),
            $manifestPath,
            $outDir,
            (string) config('sassblog.publishing.css'),
        ]);
        $process->setTimeout((float) config('sassblog.publishing.timeout', 180));
        $process->run();

        @unlink($manifestPath);

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Render estático (Node) falló: '.trim($process->getErrorOutput() ?: $process->getOutput()));
        }
    }
}
