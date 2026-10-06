<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Rendering;

/**
 * Contrato de kernel: ¿`host` es un dominio propio ACTIVO del sitio? (ADR-020 + ADR-025).
 *
 * Lo enlaza el módulo Domains. Lo usa Seo para generar el sitemap/robots de un dominio propio
 * con ese dominio como base (las <loc> deben ser del mismo host que sirve el sitemap) sin
 * depender de Domains. Sin implementación enlazada, ningún host se acepta.
 */
interface SiteHostResolver
{
    public function isActiveHost(int $siteId, string $host): bool;
}
