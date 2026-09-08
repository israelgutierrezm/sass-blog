<?php

declare(strict_types=1);

namespace App\Modules\Content\Application;

use App\Modules\Content\Events\EntryPublished;
use App\Modules\Content\Infrastructure\Models\Entry;
use Illuminate\Support\Facades\DB;

/**
 * Publica una entry. Las entries son MUTABLES (D1, sin versionado): publicar es fijar
 * status=published + published_at y emitir EntryPublished, en transacción. La
 * revalidación con perfil publish la hace el llamador (controlador) ANTES de invocar
 * este servicio. Requiere contexto de workspace activo.
 */
final class PublishEntry
{
    public function handle(Entry $entry, ?int $publishedBy = null): Entry
    {
        return DB::transaction(function () use ($entry, $publishedBy): Entry {
            $entry->status = Entry::STATUS_PUBLISHED;
            $entry->published_at = now();
            $entry->updated_by = $publishedBy;
            $entry->save();

            event(new EntryPublished(
                $entry->workspace_id,
                $entry->site_id,
                $entry->collection_id,
                $entry->id,
                $publishedBy,
            ));

            return $entry->refresh();
        });
    }
}
