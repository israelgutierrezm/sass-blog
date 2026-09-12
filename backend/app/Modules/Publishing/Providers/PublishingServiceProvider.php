<?php

declare(strict_types=1);

namespace App\Modules\Publishing\Providers;

use App\Modules\Publishing\Infrastructure\Models\Deployment;
use App\Modules\Publishing\Policies\DeploymentPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Registro del módulo Publishing (ADR-019). Registra la Policy de deployments. En
 * sub-slices posteriores: el CLI Node de render, el ensamblado del artefacto y la
 * descarga firmada.
 */
final class PublishingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Deployment::class, DeploymentPolicy::class);
    }
}
