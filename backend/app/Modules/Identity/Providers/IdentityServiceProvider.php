<?php

declare(strict_types=1);

namespace App\Modules\Identity\Providers;

use App\Modules\Identity\Console\ReprovisionRbacCommand;
use App\Modules\Identity\Listeners\ProvisionWorkspaceRbac;
use App\Modules\Shared\Domain\Tenancy\WorkspaceContext;
use App\Modules\Tenancy\Events\WorkspaceCreated;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\PermissionRegistrar;

/**
 * Registro del módulo Identity.
 *
 * Sincroniza el "team" de Spatie con el workspace activo: los roles y permisos se
 * asignan y verifican DENTRO del workspace (teams = workspace). Sin esto, un rol
 * asignado en un workspace se leería en todos.
 *
 * Se engancha a WorkspaceContext::onChange para que el cambio de contexto (HTTP,
 * job, comando) mueva el team de Spatie en el mismo instante, sin depender de que
 * alguien llame a dos métodos en orden.
 */
final class IdentityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $registrar = $this->app->make(PermissionRegistrar::class);

        $this->app->make(WorkspaceContext::class)->onChange(
            static function (?int $workspaceId) use ($registrar): void {
                $registrar->setPermissionsTeamId($workspaceId);
            }
        );

        // Efecto cruzado por evento: al crear un workspace, provisiona su RBAC.
        Event::listen(WorkspaceCreated::class, [ProvisionWorkspaceRbac::class, 'handle']);

        if ($this->app->runningInConsole()) {
            $this->commands([ReprovisionRbacCommand::class]);
        }
    }
}
