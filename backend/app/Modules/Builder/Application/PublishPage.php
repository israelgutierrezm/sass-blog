<?php

declare(strict_types=1);

namespace App\Modules\Builder\Application;

use App\Modules\Builder\Events\PagePublished;
use App\Modules\Builder\Infrastructure\Models\Page;
use App\Modules\Builder\Infrastructure\Models\PageVersion;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Publica una página con la mecánica "promover y bifurcar" (D2), en transacción:
 *
 *  1. Congela el draft actual (draft -> published): a partir de aquí es inmutable.
 *     La versión publicada son los BYTES EXACTOS que se previsualizaron.
 *  2. Crea un nuevo draft v(max+1) copiando el schema, para seguir editando.
 *  3. Rota los punteros de la página y su estado, y emite PagePublished.
 *
 * Requiere contexto de workspace activo (lo garantiza el middleware en HTTP).
 */
final class PublishPage
{
    public function handle(Page $page, ?int $publishedBy = null): Page
    {
        return DB::transaction(function () use ($page, $publishedBy): Page {
            $draft = $page->draftVersion;

            if ($draft === null) {
                throw new RuntimeException('La página no tiene un draft para publicar.');
            }

            // 1. Congelar el draft actual (permitido: su estado original es 'draft').
            $draft->status = PageVersion::STATUS_PUBLISHED;
            $draft->published_at = now();
            $draft->published_by = $publishedBy;
            $draft->save();

            // 2. Bifurcar un nuevo draft copiando el schema congelado.
            $newDraft = new PageVersion;
            $newDraft->site_id = $page->site_id;
            $newDraft->page_id = $page->id;
            $newDraft->version_number = $draft->version_number + 1;
            $newDraft->status = PageVersion::STATUS_DRAFT;
            $newDraft->schema_version = $draft->schema_version;
            $newDraft->schema = $draft->schema;
            $newDraft->created_by = $publishedBy;
            $newDraft->save();

            // 3. Rotar punteros y estado de la página.
            $page->published_version_id = $draft->id;
            $page->draft_version_id = $newDraft->id;
            $page->status = Page::STATUS_PUBLISHED;
            $page->published_at = now();
            $page->save();

            event(new PagePublished(
                $page->workspaceId(),
                $page->site_id,
                $page->id,
                $draft->id,
                $publishedBy,
            ));

            return $page->refresh();
        });
    }
}
