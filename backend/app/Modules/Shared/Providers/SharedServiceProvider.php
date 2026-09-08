<?php

declare(strict_types=1);

namespace App\Modules\Shared\Providers;

use App\Modules\Shared\Domain\Tenancy\WorkspaceContext;
use Illuminate\Support\ServiceProvider;

/**
 * Registro del shared kernel.
 *
 * El WorkspaceContext es singleton: un request, un workspace. Todo lo que
 * necesite saber "en qué workspace estamos" (el global scope, el relleno de
 * workspace_id, la resolución de capabilities) resuelve la MISMA instancia.
 */
final class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WorkspaceContext::class);
    }
}
