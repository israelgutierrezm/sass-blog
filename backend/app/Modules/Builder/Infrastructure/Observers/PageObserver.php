<?php

declare(strict_types=1);

namespace App\Modules\Builder\Infrastructure\Observers;

use App\Modules\Builder\Infrastructure\Models\Page;
use App\Modules\Shared\Domain\Rendering\PublicPathChanged;

/**
 * Observa el cambio de `path` de una página. Si la página está PUBLICADA (su URL
 * anterior era pública), emite el evento de kernel PublicPathChanged para que Seo
 * auto-cree el redirect (ADR-018). Builder no conoce a Seo: sólo anuncia el cambio.
 */
final class PageObserver
{
    public function updated(Page $page): void
    {
        // Sólo si cambió el path y la página tiene versión publicada (URL vieja viva).
        if (! $page->wasChanged('path') || $page->published_version_id === null) {
            return;
        }

        $old = (string) $page->getOriginal('path');
        $new = (string) $page->path;

        if ($old !== $new) {
            event(new PublicPathChanged($page->site_id, $old, $new));
        }
    }
}
