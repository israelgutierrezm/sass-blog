<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Rendering;

use DateTimeInterface;

/**
 * Una URL del sitemap (ADR-018), agnóstica del dominio: `path` es RELATIVO (empieza
 * por "/"); quien renderiza el sitemap le antepone la base_url del sitio. Así la misma
 * fuente sirve al SSR (Fase 4) y al build estático (Fase 5), que aplican su propia base.
 */
final class SitemapUrl
{
    public function __construct(
        public readonly string $path,
        public readonly ?DateTimeInterface $lastmod = null,
    ) {}
}
