<?php

declare(strict_types=1);

namespace App\Modules\Publishing\Application;

/**
 * Contrato del render estático (ADR-019): materializa el HTML por página + assets/styles.css
 * de un build manifest en `$outDir`. La impl de producción invoca el CLI Node (los mismos
 * site-components); los tests inyectan una impl fake. Así el orquestador del artefacto no
 * depende del proceso Node.
 */
interface StaticRenderer
{
    /**
     * @param  array<string, mixed>  $manifest
     */
    public function render(array $manifest, string $outDir): void;
}
