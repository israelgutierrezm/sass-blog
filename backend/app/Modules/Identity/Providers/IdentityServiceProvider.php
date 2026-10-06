<?php

declare(strict_types=1);

namespace App\Modules\Identity\Providers;

use App\Modules\Identity\Console\ReprovisionRbacCommand;
use App\Modules\Identity\Listeners\ProvisionWorkspaceRbac;
use App\Modules\Shared\Domain\Tenancy\WorkspaceContext;
use App\Modules\Tenancy\Events\WorkspaceCreated;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
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

        // Limiters CON NOMBRE: el nombre entra en la clave del contador, así no comparten cuenta
        // con otros endpoints de la misma IP (un `throttle:N,M` sin nombre usa sha1(dominio|ip)
        // y todos los de una IP suman en el MISMO contador). Login por email+IP: frena la fuerza
        // bruta sobre una cuenta sin bloquear a todos los de una IP compartida.
        RateLimiter::for('auth-login', fn (Request $request) => Limit::perMinute(10)
            ->by(Str::lower((string) $request->input('email')).'|'.$request->ip()));
        RateLimiter::for('auth-register', fn (Request $request) => Limit::perMinute(10)->by((string) $request->ip()));
    }
}
