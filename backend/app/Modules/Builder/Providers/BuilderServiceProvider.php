<?php

declare(strict_types=1);

namespace App\Modules\Builder\Providers;

use App\Modules\Builder\Events\PagePublished;
use App\Modules\Builder\Listeners\RecordPagePublished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Registro del módulo Builder.
 */
final class BuilderServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Efecto cruzado por evento: publicar una página deja rastro en auditoría.
        Event::listen(PagePublished::class, [RecordPagePublished::class, 'handle']);
    }
}
