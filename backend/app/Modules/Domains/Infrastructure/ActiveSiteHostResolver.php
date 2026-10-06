<?php

declare(strict_types=1);

namespace App\Modules\Domains\Infrastructure;

use App\Modules\Domains\Infrastructure\Models\SiteDomain;
use App\Modules\Shared\Domain\Rendering\SiteHostResolver;
use Illuminate\Support\Str;

/**
 * Implementación de Domains del contrato SiteHostResolver: el host es un dominio ACTIVO del
 * sitio. Búsqueda por `active_hostname` (UNIQUE, ADR-025) acotada al sitio: se usa desde
 * superficies públicas sin WorkspaceContext, y el `site_id` ya identifica al tenant.
 */
final class ActiveSiteHostResolver implements SiteHostResolver
{
    public function isActiveHost(int $siteId, string $host): bool
    {
        $host = rtrim(explode(':', Str::lower(trim($host)))[0], '.'); // sin puerto ni punto final

        return $host !== '' && SiteDomain::withoutGlobalScopes()
            ->where('active_hostname', $host)
            ->where('site_id', $siteId)
            ->exists();
    }
}
