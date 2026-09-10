<?php

declare(strict_types=1);

namespace App\Modules\Navigation\Providers;

use App\Modules\Navigation\Infrastructure\Models\Menu;
use App\Modules\Navigation\Policies\MenuPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Registro del módulo Navigation (ADR-017). Registra la Policy de menús. En sub-slices
 * posteriores enlaza el contrato de kernel SectionDataResolver (MenuResolver) para el
 * render de la sección `navigation`.
 */
final class NavigationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Menu::class, MenuPolicy::class);
    }
}
