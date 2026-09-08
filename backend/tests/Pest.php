<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\Tenancy\WorkspaceContext;
use App\Modules\Tenancy\Infrastructure\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Configuración base de Pest
|--------------------------------------------------------------------------
|
| Suites:
|   Unit          dominio puro (sin base de datos)
|   Feature       casos de uso por endpoint e integración
|   Architecture  reglas estructurales: scope de workspace, fronteras de módulos
|
| Las pruebas Feature/Architecture corren contra MySQL 8 real (base
| `sass_blog_testing`), no SQLite (ver ADR-001 y phpunit.xml).
|
*/

pest()->extend(TestCase::class)->in('Unit', 'Feature', 'Architecture');
pest()->use(RefreshDatabase::class)->in('Feature', 'Architecture');

/*
|--------------------------------------------------------------------------
| Helpers de contexto de workspace
|--------------------------------------------------------------------------
|
| El dominio exige un workspace resuelto. Fuera de HTTP (en pruebas) se fija con
| estos helpers, que reflejan lo que hace el middleware de contexto en producción.
|
*/

/**
 * Fija el workspace activo para el resto de la prueba.
 */
function actingForWorkspace(Workspace|int $workspace): int
{
    $id = $workspace instanceof Workspace ? $workspace->id : $workspace;

    app(WorkspaceContext::class)->set($id);

    return $id;
}

/**
 * Ejecuta el callback dentro del contexto de un workspace y restaura el previo.
 */
function withinWorkspace(Workspace|int $workspace, Closure $callback): mixed
{
    $id = $workspace instanceof Workspace ? $workspace->id : $workspace;

    return app(WorkspaceContext::class)->runFor($id, $callback);
}
