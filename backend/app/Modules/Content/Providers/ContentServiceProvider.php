<?php

declare(strict_types=1);

namespace App\Modules\Content\Providers;

use App\Modules\Content\Events\EntryPublished;
use App\Modules\Content\Infrastructure\Models\Author;
use App\Modules\Content\Infrastructure\Models\Category;
use App\Modules\Content\Infrastructure\Models\Collection;
use App\Modules\Content\Infrastructure\Models\Entry;
use App\Modules\Content\Listeners\ProvisionArticleContent;
use App\Modules\Content\Listeners\RecordEntryPublished;
use App\Modules\Content\Policies\AuthorPolicy;
use App\Modules\Content\Policies\CategoryPolicy;
use App\Modules\Content\Policies\CollectionPolicy;
use App\Modules\Content\Policies\EntryPolicy;
use App\Modules\Sites\Events\SiteCreated;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Registro del módulo Content (CMS). Escucha SiteCreated para sembrar el preset de
 * artículos y registra las Policies de autores/categorías. En sub-slices posteriores
 * enlaza los contratos de kernel (SectionDataResolver, DynamicRouteResolver) y
 * registra EntryPublished -> auditoría.
 */
final class ContentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(SiteCreated::class, [ProvisionArticleContent::class, 'handle']);
        Event::listen(EntryPublished::class, [RecordEntryPublished::class, 'handle']);

        Gate::policy(Author::class, AuthorPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Collection::class, CollectionPolicy::class);
        Gate::policy(Entry::class, EntryPolicy::class);
    }
}
