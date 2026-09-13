<?php

declare(strict_types=1);

namespace App\Modules\Publishing\Providers;

use App\Modules\Publishing\Application\StaticRenderer;
use App\Modules\Publishing\Infrastructure\Models\Deployment;
use App\Modules\Publishing\Infrastructure\NodeStaticRenderer;
use App\Modules\Publishing\Policies\DeploymentPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Registro del módulo Publishing (ADR-019). Enlaza el render estático (Node en producción)
 * y registra la Policy de deployments.
 */
final class PublishingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(StaticRenderer::class, NodeStaticRenderer::class);
    }

    public function boot(): void
    {
        Gate::policy(Deployment::class, DeploymentPolicy::class);
    }
}
