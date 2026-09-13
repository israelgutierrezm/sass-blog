<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Publishing\Application\StaticRenderer;

/**
 * Render estático de prueba (sin Node): escribe HTML por página (con el og_image, para
 * probar la reescritura de media) + assets/styles.css a clean paths.
 */
final class FakeStaticRenderer implements StaticRenderer
{
    public function render(array $manifest, string $outDir): void
    {
        foreach ($manifest['pages'] as $page) {
            $clean = trim((string) $page['path'], '/');
            $file = $outDir.'/'.($clean === '' ? 'index.html' : $clean.'/index.html');
            @mkdir(dirname($file), 0777, true);
            $og = $page['render']['seo']['og_image'] ?? '';
            $img = is_string($og) && $og !== '' ? "<img src=\"{$og}\">" : '';
            file_put_contents($file, "<!doctype html><html><body>{$page['path']}{$img}</body></html>");
        }
        @mkdir($outDir.'/assets', 0777, true);
        file_put_contents($outDir.'/assets/styles.css', '/* css */');
    }
}
