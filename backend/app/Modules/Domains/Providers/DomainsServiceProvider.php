<?php

declare(strict_types=1);

namespace App\Modules\Domains\Providers;

use App\Modules\Domains\Application\DnsResolver;
use App\Modules\Domains\Infrastructure\ActiveSiteHostResolver;
use App\Modules\Domains\Infrastructure\AutoVerifyDnsResolver;
use App\Modules\Domains\Infrastructure\Models\SiteDomain;
use App\Modules\Domains\Infrastructure\SystemDnsResolver;
use App\Modules\Domains\Policies\DomainPolicy;
use App\Modules\Shared\Domain\Rendering\SiteHostResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * Registro del módulo Domains (ADR-020 + ADR-025). Enlaza el DnsResolver (DNS del sistema en
 * producción), registra la Policy y el limiter con nombre de la superficie pública
 * (`resolve`/`tls-check`).
 */
final class DomainsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Prod: DNS del sistema. E2E/staging (auto_verify): verifica sin DNS real. NUNCA en
        // producción, aunque se active por error: saltaría la prueba de propiedad (ADR-025).
        $this->app->bind(DnsResolver::class, config('sassblog.domains.auto_verify') && ! $this->app->isProduction()
            ? AutoVerifyDnsResolver::class
            : SystemDnsResolver::class);

        // Contrato de kernel: Seo pregunta si un host es dominio activo de un sitio (sitemap/robots).
        $this->app->bind(SiteHostResolver::class, ActiveSiteHostResolver::class);
    }

    public function boot(): void
    {
        Gate::policy(SiteDomain::class, DomainPolicy::class);

        // `resolve`/`tls-check` los llaman el renderer y el edge server-to-server: TODO el tráfico
        // llega de UNA IP, así que limitar por IP tumbaría el enrutado de todos los dominios a la
        // vez. Se limita por HOST consultado (cada dominio su propio cubo) y con nombre propio
        // (no comparte contador con otros endpoints). En prod, además, sólo red del edge.
        RateLimiter::for('domains-public', fn (Request $request) => Limit::perMinute(600)
            ->by(Str::lower((string) ($request->query('host') ?? $request->query('domain', '')))));
    }
}
