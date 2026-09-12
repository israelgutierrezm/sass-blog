<?php

declare(strict_types=1);

namespace App\Modules\Shared\Providers;

use App\Modules\Shared\Domain\Rendering\CompositeSectionDataResolver;
use App\Modules\Shared\Domain\Rendering\SectionDataResolver;
use App\Modules\Shared\Domain\Tenancy\WorkspaceContext;
use Illuminate\Support\ServiceProvider;

/**
 * Registro del shared kernel.
 *
 * El WorkspaceContext es singleton: un request, un workspace. Todo lo que
 * necesite saber "en qué workspace estamos" (el global scope, el relleno de
 * workspace_id, la resolución de capabilities) resuelve la MISMA instancia.
 *
 * SectionDataResolver se resuelve como un COMPOSITE de todas las implementaciones
 * etiquetadas por los módulos de dominio (Content, Navigation): así conviven varios
 * resolvers de sección bajo el único contrato de kernel (ADR-013).
 */
final class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WorkspaceContext::class);

        $this->app->singleton(
            SectionDataResolver::class,
            fn ($app) => new CompositeSectionDataResolver(
                array_values(iterator_to_array($app->tagged(SectionDataResolver::TAG))),
            ),
        );
    }
}
