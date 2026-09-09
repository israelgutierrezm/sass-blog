<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Observers;

use App\Modules\Content\Infrastructure\Models\Collection;
use App\Modules\Content\Infrastructure\Models\Entry;
use App\Modules\Shared\Domain\Rendering\PublicPathChanged;

/**
 * Observa el cambio de `slug` de una entry. Si la entry está PUBLICADA y su colección
 * es enrutable (`/{route_prefix}/{slug}`, ADR-011), emite el evento de kernel
 * PublicPathChanged para que Seo auto-cree el redirect (ADR-018). Content compone las
 * rutas públicas viejas/nuevas (conoce el prefijo); no conoce a Seo.
 */
final class EntryObserver
{
    public function updated(Entry $entry): void
    {
        if (! $entry->wasChanged('slug') || $entry->status !== Entry::STATUS_PUBLISHED) {
            return;
        }

        $collection = Collection::query()->whereKey($entry->collection_id)->first();

        // Sin prefijo o sin plantilla publicada la entry no tiene URL de detalle propia.
        if ($collection === null || $collection->route_prefix === null || $collection->template_page_id === null) {
            return;
        }

        $old = '/'.$collection->route_prefix.'/'.$entry->getOriginal('slug');
        $new = '/'.$collection->route_prefix.'/'.$entry->slug;

        if ($old !== $new) {
            event(new PublicPathChanged($entry->site_id, $old, $new));
        }
    }
}
