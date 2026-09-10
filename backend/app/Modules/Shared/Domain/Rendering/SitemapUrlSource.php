<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Rendering;

/**
 * Contrato de KERNEL: una fuente que aporta URLs públicas de un sitio al sitemap
 * (ADR-018). Cada módulo de dominio lo implementa (Builder → páginas publicadas,
 * Content → entries publicadas enrutables) y ETIQUETA su implementación con TAG; Seo
 * recolecta todas las fuentes etiquetadas sin conocer los módulos. Sin un módulo, su
 * fuente no está etiquetada y simplemente no aporta URLs (degradación elegante).
 */
interface SitemapUrlSource
{
    /** Tag del contenedor bajo el que cada módulo registra su fuente. */
    public const TAG = 'seo.sitemap.sources';

    /**
     * URLs públicas del sitio, corriendo dentro del WorkspaceContext del render.
     *
     * @return iterable<SitemapUrl>
     */
    public function urlsForSite(int $siteId): iterable;
}
