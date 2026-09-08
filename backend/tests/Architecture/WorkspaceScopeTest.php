<?php

declare(strict_types=1);

use App\Modules\Audit\Infrastructure\Models\AuditLog;
use App\Modules\Billing\Infrastructure\Models\Capability;
use App\Modules\Billing\Infrastructure\Models\Plan;
use App\Modules\Billing\Infrastructure\Models\Subscription;
use App\Modules\Shared\Domain\Tenancy\Concerns\BelongsToWorkspace;
use App\Modules\Sites\Infrastructure\Models\Site;
use App\Modules\Tenancy\Infrastructure\Models\Workspace;
use App\Modules\Tenancy\Infrastructure\Models\WorkspaceMember;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;

/**
 * Candado estructural (ADR-001): todo modelo de dominio scopeado por workspace
 * DEBE declarar BelongsToWorkspace. Si alguien agrega un modelo y olvida el scope,
 * esta prueba falla — antes de que la fuga llegue a producción.
 *
 * Excepciones declaradas (NO scopeadas, con razón):
 *   Workspace   → es la raíz de tenencia, no está dentro de otro.
 *   Plan        → catálogo global.
 *   Capability  → catálogo global.
 *   AuditLog    → inmutable; workspace_id nullable (acciones de plataforma).
 */

/** @return list<class-string<Model>> */
function domainModelClasses(): array
{
    $appPath = str_replace('\\', '/', app_path());
    $classes = [];

    foreach (File::allFiles(app_path('Modules')) as $file) {
        $path = str_replace('\\', '/', $file->getPathname());

        if (! str_contains($path, '/Infrastructure/Models/') || $file->getExtension() !== 'php') {
            continue;
        }

        $relative = trim(substr($path, strlen($appPath)), '/');
        $class = 'App\\'.str_replace('/', '\\', substr($relative, 0, -4));

        if (class_exists($class) && is_subclass_of($class, Model::class)) {
            $classes[] = $class;
        }
    }

    return $classes;
}

it('descubre modelos de dominio (el escáner no puede quedar vacío)', function () {
    expect(domainModelClasses())->not->toBeEmpty();
});

it('todo modelo de dominio scopeado declara BelongsToWorkspace', function () {
    $nonScoped = [
        Workspace::class,
        Plan::class,
        Capability::class,
        AuditLog::class,
    ];

    $violations = [];

    foreach (domainModelClasses() as $class) {
        $isScoped = in_array(BelongsToWorkspace::class, class_uses_recursive($class), true);
        $shouldBeScoped = ! in_array($class, $nonScoped, true);

        if ($shouldBeScoped && ! $isScoped) {
            $violations[] = $class;
        }
    }

    expect($violations)->toBe([]);
});

it('los modelos scopeados conocidos SÍ llevan el scope', function () {
    foreach ([Site::class, WorkspaceMember::class, Subscription::class] as $class) {
        expect(class_uses_recursive($class))->toContain(BelongsToWorkspace::class);
    }
});
