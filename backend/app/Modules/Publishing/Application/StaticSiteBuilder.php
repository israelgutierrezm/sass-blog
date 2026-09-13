<?php

declare(strict_types=1);

namespace App\Modules\Publishing\Application;

use App\Modules\Seo\Application\SitemapDocument;
use App\Modules\Seo\Application\SitemapGenerator;
use App\Modules\Sites\Infrastructure\Models\Site;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Ensambla el artefacto estático de un sitio (ADR-019): manifest → render (CLI Node, los
 * mismos site-components) → copia de media + reescritura de sus URLs a rutas del artefacto
 * → sitemap.xml/robots.txt (reusa Seo) → ZIP en el disco de publishing. Self-contained:
 * assets/styles.css, assets/media/… y las páginas a clean paths.
 */
final class StaticSiteBuilder
{
    public function __construct(
        private readonly BuildManifest $manifest,
        private readonly StaticRenderer $renderer,
        private readonly SitemapGenerator $sitemap,
    ) {}

    /**
     * @return array{artifact_ref: string, bytes: int}
     */
    public function build(Site $site, string $ulid): array
    {
        $manifest = $this->manifest->forSite($site);
        $outDir = storage_path('app'.DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.'deploy-'.$ulid);
        File::deleteDirectory($outDir);
        File::ensureDirectoryExists($outDir);

        try {
            $this->renderer->render($manifest, $outDir); // HTML por página + assets/styles.css
            $this->assembleMedia(is_array($manifest['media'] ?? null) ? $manifest['media'] : [], $outDir);
            $this->writeSeoDocs($site, $outDir);

            return $this->package($outDir, $ulid);
        } finally {
            File::deleteDirectory($outDir);
        }
    }

    /**
     * Copia la media referenciada a assets/… y reescribe sus URLs en el HTML a rutas
     * relativas del artefacto (self-contained).
     *
     * @param  list<string>  $mediaUrls
     */
    private function assembleMedia(array $mediaUrls, string $outDir): void
    {
        $disk = (string) config('sassblog.media.disk', 'public');
        $storage = Storage::disk($disk);
        $basePath = rtrim((string) (parse_url((string) $storage->url(''), PHP_URL_PATH) ?: $storage->url('')), '/');

        $rewrites = [];
        foreach ($mediaUrls as $url) {
            if (! is_string($url)) {
                continue;
            }
            $urlPath = (string) (parse_url($url, PHP_URL_PATH) ?: $url);
            if ($basePath !== '' && ! str_starts_with($urlPath, $basePath.'/')) {
                continue;
            }
            $diskPath = ltrim(substr($urlPath, strlen($basePath)), '/'); // media/…
            if ($diskPath === '' || ! $storage->exists($diskPath)) {
                continue;
            }
            $artifactPath = 'assets/'.$diskPath; // assets/media/…
            $dest = $outDir.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $artifactPath);
            File::ensureDirectoryExists(dirname($dest));
            File::put($dest, (string) $storage->get($diskPath));
            $rewrites[$url] = $artifactPath;
        }

        if ($rewrites !== []) {
            $this->rewriteHtml($outDir, $rewrites);
        }
    }

    /**
     * @param  array<string, string>  $rewrites  url => ruta del artefacto (root-relativa)
     */
    private function rewriteHtml(string $outDir, array $rewrites): void
    {
        foreach (File::allFiles($outDir) as $file) {
            if ($file->getExtension() !== 'html') {
                continue;
            }
            $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($outDir) + 1));
            $prefix = str_repeat('../', substr_count($rel, '/')); // profundidad del fichero
            $html = File::get($file->getPathname());
            foreach ($rewrites as $url => $artifactPath) {
                $html = str_replace($url, $prefix.$artifactPath, $html);
            }
            File::put($file->getPathname(), $html);
        }
    }

    private function writeSeoDocs(Site $site, string $outDir): void
    {
        $settings = is_array($site->settings) ? $site->settings : [];
        $baseUrl = isset($settings['base_url']) && is_string($settings['base_url']) ? $settings['base_url'] : '';
        $urls = $this->sitemap->forSite($site->id);

        File::put($outDir.DIRECTORY_SEPARATOR.'sitemap.xml', SitemapDocument::xml($urls, $baseUrl));
        File::put($outDir.DIRECTORY_SEPARATOR.'robots.txt', SitemapDocument::robots($baseUrl));
    }

    /**
     * @return array{artifact_ref: string, bytes: int}
     */
    private function package(string $outDir, string $ulid): array
    {
        $disk = (string) config('sassblog.publishing.disk', 'local');
        $storage = Storage::disk($disk);
        $storage->makeDirectory('deployments');

        $ref = 'deployments/'.$ulid.'.zip';
        $zipPath = $storage->path($ref);
        File::ensureDirectoryExists(dirname($zipPath));

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el artefacto ZIP.');
        }
        foreach (File::allFiles($outDir) as $file) {
            $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($outDir) + 1));
            $zip->addFile($file->getPathname(), $rel);
        }
        $zip->close();

        return ['artifact_ref' => $ref, 'bytes' => (int) $storage->size($ref)];
    }
}
