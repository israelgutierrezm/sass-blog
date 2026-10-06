<?php

declare(strict_types=1);

namespace App\Modules\Seo\Providers;

use App\Modules\Seo\Infrastructure\Models\Redirect;
use App\Modules\Seo\Infrastructure\Rendering\SeoRedirectResolver;
use App\Modules\Seo\Listeners\RegisterPathChangeRedirect;
use App\Modules\Seo\Policies\RedirectPolicy;
use App\Modules\Shared\Domain\Rendering\PublicPathChanged;
use App\Modules\Shared\Domain\Rendering\RedirectResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * Registro del módulo Seo (ADR-018). Enlaza el contrato de kernel RedirectResolver,
 * registra la Policy de redirects, escucha PublicPathChanged para auto-crear los
 * redirects de slug-history y define el limiter de las rutas públicas de sitemap/robots.
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

        Event::listen(PublicPathChanged::class, [RegisterPathChangeRedirect::class, 'handle']);

        // sitemap/robots los pide el renderer (pocas IPs): cupo por SITIO, no por IP (ADR-026).
        RateLimiter::for('seo-public', fn (Request $request) => Limit::perMinute(120)
            ->by(Str::upper((string) $request->route('site'))));
    }
}
