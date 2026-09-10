<?php

declare(strict_types=1);

namespace App\Modules\Seo\Application;

use App\Modules\Shared\Domain\Rendering\SitemapUrl;
use App\Modules\Shared\Domain\Rendering\SitemapUrlSource;

/**
 * Recolecta las URLs públicas de un sitio de TODAS las fuentes de kernel etiquetadas
 * (ADR-018), sin conocer los módulos que las aportan. Pieza reutilizable: el SSR de
 * Fase 4 y el build estático de Fase 5 consumen la misma lista.
 */
final class SitemapGenerator
{
    /**
     * @return list<SitemapUrl>
     */
    public function forSite(int $siteId): array
    {
        $urls = [];

        /** @var SitemapUrlSource $source */
        foreach (app()->tagged(SitemapUrlSource::TAG) as $source) {
            foreach ($source->urlsForSite($siteId) as $url) {
                $urls[] = $url;
            }
        }

        return $urls;
    }
}
