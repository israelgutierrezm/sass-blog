<?php

declare(strict_types=1);

namespace App\Modules\Builder\Application;

use App\Modules\Builder\Infrastructure\Models\Page;
use App\Modules\Builder\Infrastructure\Models\PageVersion;
use App\Modules\Sites\Infrastructure\Models\Site;
use Illuminate\Support\Facades\DB;

/**
 * Crea una página con su primera versión draft (v1, schema vacío) y fija el
 * puntero draft_version_id. En una transacción. workspace_id lo rellena
 * BelongsToWorkspace desde el contexto; site_id se pasa explícito.
 */
final class CreatePage
{
    public function handle(Site $site, string $title, string $path, ?int $createdBy = null): Page
    {
        return DB::transaction(function () use ($site, $title, $path, $createdBy): Page {
            $page = new Page;
            $page->site_id = $site->id;
            $page->title = $title;
            $page->path = $path;
            $page->status = Page::STATUS_DRAFT;
            $page->created_by = $createdBy;
            $page->save();

            $draft = new PageVersion;
            $draft->site_id = $site->id;
            $draft->page_id = $page->id;
            $draft->version_number = 1;
            $draft->status = PageVersion::STATUS_DRAFT;
            $draft->schema_version = 1;
            $draft->schema = ['schema_version' => 1, 'sections' => []];
            $draft->created_by = $createdBy;
            $draft->save();

            // Puntero directo (no fillable): lo mueve la lógica, no el cliente.
            $page->draft_version_id = $draft->id;
            $page->save();

            return $page->refresh();
        });
    }
}
