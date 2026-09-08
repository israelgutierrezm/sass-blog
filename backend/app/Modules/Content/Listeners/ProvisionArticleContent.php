<?php

declare(strict_types=1);

namespace App\Modules\Content\Listeners;

use App\Modules\Builder\Application\CreateCollectionTemplate;
use App\Modules\Content\Application\CreateCollection;
use App\Modules\Content\Domain\Presets\ArticleCollectionPreset;
use App\Modules\Content\Infrastructure\Models\Author;
use App\Modules\Content\Infrastructure\Models\Category;
use App\Modules\Content\Infrastructure\Models\Collection;
use App\Modules\Shared\Domain\Tenancy\WorkspaceContext;
use App\Modules\Sites\Events\SiteCreated;
use App\Modules\Sites\Infrastructure\Models\Site;

/**
 * Siembra el preset de artículos al crear un site: la colección `articles` con sus
 * campos, su Page plantilla de detalle (kind=collection_template), un autor y una
 * categoría por defecto. Síncrono e idempotente: si el site ya tiene la colección
 * `articles`, no hace nada.
 */
final class ProvisionArticleContent
{
    public function __construct(
        private readonly WorkspaceContext $context,
        private readonly CreateCollection $createCollection,
        private readonly CreateCollectionTemplate $createTemplate,
    ) {}

    public function handle(SiteCreated $event): void
    {
        $this->context->runFor($event->workspaceId, function () use ($event): void {
            $site = Site::findOrFail($event->siteId);

            $alreadySeeded = Collection::query()
                ->where('site_id', $site->id)
                ->where('handle', 'articles')
                ->exists();

            if ($alreadySeeded) {
                return;
            }

            $preset = ArticleCollectionPreset::definition();
            $collection = $this->createCollection->handle($site, $preset['attributes'], $preset['fields']);

            // Plantilla de detalle (Page kind=collection_template) enlazada a la colección.
            $template = $this->createTemplate->handle($site, 'Plantilla de artículo', ArticleCollectionPreset::templateSchema());
            $collection->template_page_id = $template->id;
            $collection->save();

            Author::create([
                'site_id' => $site->id,
                'name' => 'Redacción',
                'slug' => 'redaccion',
            ]);

            Category::create([
                'site_id' => $site->id,
                'collection_id' => $collection->id,
                'name' => 'General',
                'slug' => 'general',
                'position' => 0,
            ]);
        });
    }
}
