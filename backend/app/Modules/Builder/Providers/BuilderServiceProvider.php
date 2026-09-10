<?php

declare(strict_types=1);

namespace App\Modules\Builder\Providers;

use App\Modules\Builder\Events\PagePublished;
use App\Modules\Builder\Infrastructure\Models\Page;
use App\Modules\Builder\Infrastructure\Observers\PageObserver;
use App\Modules\Builder\Infrastructure\Rendering\PageSitemapSource;
use App\Modules\Builder\Listeners\RecordPagePublished;
use App\Modules\Builder\Policies\PagePolicy;
use App\Modules\Shared\Domain\Rendering\SitemapUrlSource;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Registro del módulo Builder.
 */
final class BuilderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Aporta las páginas publicadas al sitemap (contrato de kernel), sin que Seo
        // conozca al Builder. Sin Seo, la etiqueta simplemente no se consume.
        $this->app->tag([PageSitemapSource::class], SitemapUrlSource::TAG);
    }

    public function boot(): void
    {
        Gate::policy(Page::class, PagePolicy::class);

        // Efecto cruzado por evento: publicar una página deja rastro en auditoría.
        Event::listen(PagePublished::class, [RecordPagePublished::class, 'handle']);

        // Cambiar el path de una página publicada anuncia PublicPathChanged (kernel)
        // para el slug-history de Seo, sin acoplar Builder a Seo.
        Page::observe(PageObserver::class);
    }
}
