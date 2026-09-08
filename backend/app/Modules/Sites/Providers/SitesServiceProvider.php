<?php

declare(strict_types=1);

namespace App\Modules\Sites\Providers;

use App\Modules\Sites\Infrastructure\Models\Site;
use App\Modules\Sites\Policies\SitePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Registro del módulo Sites. La policy se registra explícitamente porque el modelo
 * vive en un módulo (la autodetección de Laravel busca en App\Policies).
 */
final class SitesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Site::class, SitePolicy::class);
    }
}
