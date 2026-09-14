<?php

declare(strict_types=1);

namespace App\Modules\Newsletter\Providers;

use App\Modules\Newsletter\Infrastructure\Models\Campaign;
use App\Modules\Newsletter\Infrastructure\Models\Subscriber;
use App\Modules\Newsletter\Policies\NewsletterPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Registro del módulo Newsletter (ADR-022). Autoriza la gestión de suscriptores y campañas por
 * la Policy. En sub-slices posteriores: endpoints públicos (subscribe/confirm/unsubscribe),
 * el job de envío y la API/admin.
 */
final class NewsletterServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Subscriber::class, NewsletterPolicy::class);
        Gate::policy(Campaign::class, NewsletterPolicy::class);
    }
}
