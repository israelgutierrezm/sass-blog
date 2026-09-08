<?php

declare(strict_types=1);

namespace App\Modules\Content\Providers;

use App\Modules\Content\Listeners\ProvisionArticleContent;
use App\Modules\Sites\Events\SiteCreated;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Registro del módulo Content (CMS). Escucha SiteCreated para sembrar el preset de
 * artículos. En sub-slices posteriores enlaza los contratos de kernel
 * (SectionDataResolver, DynamicRouteResolver) y registra EntryPublished -> auditoría.
 */
final class ContentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(SiteCreated::class, [ProvisionArticleContent::class, 'handle']);
    }
}
