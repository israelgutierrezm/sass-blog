<?php

declare(strict_types=1);

namespace App\Modules\Builder\Providers;

use App\Modules\Builder\Events\PagePublished;
use App\Modules\Builder\Infrastructure\Models\Page;
use App\Modules\Builder\Listeners\RecordPagePublished;
use App\Modules\Builder\Policies\PagePolicy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Registro del módulo Builder.
 */
final class BuilderServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Page::class, PagePolicy::class);

        // Efecto cruzado por evento: publicar una página deja rastro en auditoría.
        Event::listen(PagePublished::class, [RecordPagePublished::class, 'handle']);
    }
}
