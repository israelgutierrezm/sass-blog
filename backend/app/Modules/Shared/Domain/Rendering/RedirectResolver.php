<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Rendering;

/**
 * Contrato de kernel para resolver un redirect de una ruta pública ANTES del 404
 * (ADR-018). Lo enlaza el módulo Seo; si NO hay implementación, el render simplemente
 * no redirige (Builder no depende de Seo).
 */
interface RedirectResolver
{
    /**
     * @return array{to: string, status: int}|null
     */
    public function resolve(int $siteId, string $path): ?array;
}
