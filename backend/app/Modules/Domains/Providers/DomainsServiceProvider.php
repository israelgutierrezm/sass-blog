<?php

declare(strict_types=1);

namespace App\Modules\Domains\Providers;

use App\Modules\Domains\Application\DnsResolver;
use App\Modules\Domains\Infrastructure\Models\SiteDomain;
use App\Modules\Domains\Infrastructure\SystemDnsResolver;
use App\Modules\Domains\Policies\DomainPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Registro del módulo Domains (ADR-020). Enlaza el DnsResolver (DNS del sistema en
 * producción) y registra la Policy. En sub-slices posteriores: los endpoints públicos
 * `resolve`/`tls-check` y el enrutado por Host del renderer.
 */
final class DomainsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DnsResolver::class, SystemDnsResolver::class);
    }

    public function boot(): void
    {
        Gate::policy(SiteDomain::class, DomainPolicy::class);
    }
}
