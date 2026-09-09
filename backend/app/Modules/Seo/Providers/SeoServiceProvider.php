<?php

declare(strict_types=1);

namespace App\Modules\Seo\Providers;

use App\Modules\Seo\Infrastructure\Models\Redirect;
use App\Modules\Seo\Infrastructure\Rendering\SeoRedirectResolver;
use App\Modules\Seo\Policies\RedirectPolicy;
use App\Modules\Shared\Domain\Rendering\RedirectResolver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Registro del módulo Seo (ADR-018). Enlaza el contrato de kernel RedirectResolver y
 * registra la Policy de redirects. En sub-slices posteriores: listener de
 * slug-history y las rutas públicas de sitemap/robots.
 */
final class SeoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RedirectResolver::class, SeoRedirectResolver::class);
    }

    public function boot(): void
    {
        Gate::policy(Redirect::class, RedirectPolicy::class);
    }
}
