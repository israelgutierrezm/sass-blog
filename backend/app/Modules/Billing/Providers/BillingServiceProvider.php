<?php

declare(strict_types=1);

namespace App\Modules\Billing\Providers;

use App\Modules\Billing\Application\PlanCapabilityResolver;
use App\Modules\Billing\Listeners\StartFreeSubscription;
use App\Modules\Shared\Domain\Capabilities\CapabilityResolver;
use App\Modules\Tenancy\Events\WorkspaceCreated;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Registro del módulo Billing.
 *
 * Enlaza el contrato del kernel CapabilityResolver con la implementación basada en
 * planes/suscripciones. El kernel razona sobre capabilities sin conocer Billing.
 */
final class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CapabilityResolver::class, PlanCapabilityResolver::class);
    }

    public function boot(): void
    {
        // Al crear un workspace, arranca su suscripción free.
        Event::listen(WorkspaceCreated::class, [StartFreeSubscription::class, 'handle']);
    }
}
