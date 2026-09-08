<?php

declare(strict_types=1);

namespace App\Modules\Builder\Application;

use App\Modules\Builder\Infrastructure\Models\Page;
use App\Modules\Builder\Infrastructure\Models\PageVersion;
use App\Modules\Sites\Infrastructure\Models\Site;
use Illuminate\Support\Facades\DB;

/**
 * Crea una Page plantilla de detalle de colección (kind=collection_template, sin
 * path) ya PUBLICADA, con el schema dado. El schema lleva placeholders de binding
 * ({ "$bind": "entry.…" }) y por eso NO pasa por la validación estricta del page
 * schema (se resuelven en render, ADR-011/012); es una plantilla del sistema
 * (sembrada por un preset), no contenido editado por el cliente.
 *
 * Content depende de Builder y crea la plantilla a través de este servicio
 * sancionado, sin tocar las tablas de Builder directamente.
 */
final class CreateCollectionTemplate
{
    /**
     * @param  array<string, mixed>  $schema
     */
    public function handle(Site $site, string $title, array $schema, ?int $createdBy = null): Page
    {
        return DB::transaction(function () use ($site, $title, $schema, $createdBy): Page {
            $schemaVersion = is_int($schema['schema_version'] ?? null) ? $schema['schema_version'] : 1;

            $page = new Page;
            $page->site_id = $site->id;
            $page->title = $title;
            $page->kind = Page::KIND_COLLECTION_TEMPLATE; // no fillable: lo fija la lógica
            // path queda NULL (invariante del CHECK para collection_template).
            $page->status = Page::STATUS_PUBLISHED;
            $page->created_by = $createdBy;
            $page->published_at = now();
            $page->save();

            $published = new PageVersion;
            $published->site_id = $site->id;
            $published->page_id = $page->id;
            $published->version_number = 1;
            $published->status = PageVersion::STATUS_PUBLISHED;
            $published->schema_version = $schemaVersion;
            $published->schema = $schema;
            $published->published_at = now();
            $published->published_by = $createdBy;
            $published->created_by = $createdBy;
            $published->save();

            // Draft bifurcado (v2) para edición futura de la plantilla.
            $draft = new PageVersion;
            $draft->site_id = $site->id;
            $draft->page_id = $page->id;
            $draft->version_number = 2;
            $draft->status = PageVersion::STATUS_DRAFT;
            $draft->schema_version = $schemaVersion;
            $draft->schema = $schema;
            $draft->created_by = $createdBy;
            $draft->save();

            $page->published_version_id = $published->id;
            $page->draft_version_id = $draft->id;
            $page->save();

            return $page->refresh();
        });
    }
}
