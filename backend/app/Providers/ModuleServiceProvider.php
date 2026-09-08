<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Cargador del monolito modular.
 *
 * Recorre el registro declarativo de config/sassblog.php —nunca el sistema de
 * archivos— y engancha, si existen, los artefactos de cada módulo:
 *
 *   app/Modules/{Modulo}/database/migrations   → migraciones del módulo
 *   app/Modules/{Modulo}/Providers/{Modulo}ServiceProvider.php
 *   app/Modules/{Modulo}/Http/Routes/api.php    → prefijo api/v1, middleware api
 *   app/Modules/{Modulo}/Http/Routes/web.php    → middleware web
 *
 * Por qué el registro y no un glob del disco: una carpeta creada por error o a
 * medio renombrar no debe convertirse en un módulo cargado en silencio.
 */
final class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach ($this->moduleNames() as $module) {
            $provider = "App\\Modules\\{$module}\\Providers\\{$module}ServiceProvider";

            if (class_exists($provider)) {
                $this->app->register($provider);
            }
        }
    }

    public function boot(): void
    {
        foreach ($this->moduleNames() as $module) {
            $path = $this->app->path("Modules/{$module}");

            $migrations = "{$path}/database/migrations";

            if (is_dir($migrations)) {
                $this->loadMigrationsFrom($migrations);
            }

            $this->registerRoutes($path);
        }
    }

    /**
     * @return list<string>
     */
    private function moduleNames(): array
    {
        /** @var array<string, array<string, mixed>> $modules */
        $modules = (array) config('sassblog.modules', []);

        return array_keys($modules);
    }

    private function registerRoutes(string $path): void
    {
        $api = "{$path}/Http/Routes/api.php";

        if (is_file($api)) {
            Route::middleware('api')
                ->prefix((string) config('sassblog.api.prefix'))
                ->name((string) config('sassblog.api.name_prefix'))
                ->group($api);
        }

        $web = "{$path}/Http/Routes/web.php";

        if (is_file($web)) {
            Route::middleware('web')->group($web);
        }
    }
}
