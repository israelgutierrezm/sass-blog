<?php

declare(strict_types=1);

namespace App\Modules\Seo\Application;

use App\Modules\Shared\Domain\Rendering\SitemapUrl;

/**
 * Renderiza los documentos sitemap.xml y robots.txt (ADR-018). Reutilizable: lo consume
 * el endpoint público (SitemapController) y el build estático (Publishing), para que el
 * SSR y el artefacto produzcan EXACTAMENTE lo mismo.
 */
final class SitemapDocument
{
    /**
     * @param  list<SitemapUrl>  $urls
     */
    public static function xml(array $urls, string $baseUrl): string
    {
        $base = rtrim($baseUrl, '/');
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($urls as $url) {
            $loc = htmlspecialchars($base.$url->path, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $xml .= '  <url><loc>'.$loc.'</loc>';
            if ($url->lastmod !== null) {
                $xml .= '<lastmod>'.$url->lastmod->format('Y-m-d').'</lastmod>';
            }
            $xml .= '</url>'."\n";
        }

        return $xml.'</urlset>'."\n";
    }

    public static function robots(string $baseUrl): string
    {
        $base = rtrim($baseUrl, '/');

        return "User-agent: *\nAllow: /\n\nSitemap: {$base}/sitemap.xml\n";
    }
}
