<?php

declare(strict_types=1);

namespace App\Modules\Navigation\Providers;

use App\Modules\Navigation\Infrastructure\Models\Menu;
use App\Modules\Navigation\Infrastructure\Rendering\MenuResolver;
use App\Modules\Navigation\Policies\MenuPolicy;
use App\Modules\Shared\Domain\Rendering\SectionDataResolver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Registro del módulo Navigation (ADR-017). Registra la Policy de menús y aporta su
 * resolver de sección (`navigation`) al composite de kernel SectionDataResolver, para
 * embeber el árbol del menú en el sidecar `resolved` del render.
 */
final class NavigationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([MenuResolver::class], SectionDataResolver::TAG);
    }

    public function boot(): void
    {
        Gate::policy(Menu::class, MenuPolicy::class);
    }
}
