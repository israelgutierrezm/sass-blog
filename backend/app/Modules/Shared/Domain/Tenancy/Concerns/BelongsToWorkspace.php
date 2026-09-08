<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Tenancy\Concerns;

use App\Modules\Shared\Domain\Tenancy\Exceptions\WorkspaceMismatchException;
use App\Modules\Shared\Domain\Tenancy\WorkspaceContext;
use App\Modules\Shared\Domain\Tenancy\WorkspaceScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Convierte un modelo Eloquent en modelo de dominio acotado por workspace (ADR-001).
 *
 * Hace tres cosas, y las tres hacen falta:
 *
 * 1. Registra el global scope, para que ninguna lectura cruce workspaces.
 * 2. Rellena `workspace_id` al crear, para que ninguna escritura dependa de que el
 *    programador se acuerde.
 * 3. Bloquea el cambio de `workspace_id` al actualizar. El scope protege las
 *    lecturas; sin esto un update podría trasladar una fila al workspace de otro.
 *
 * Los puntos 2 y 3 son los que suelen faltar en implementaciones caseras de
 * multi-tenancy, y son justo los que fallan en silencio.
 */
trait BelongsToWorkspace
{
    public static function bootBelongsToWorkspace(): void
    {
        static::addGlobalScope(app(WorkspaceScope::class));

        static::creating(function (Model $model): void {
            if ($model->getAttribute(WorkspaceScope::COLUMN) === null) {
                $model->setAttribute(WorkspaceScope::COLUMN, app(WorkspaceContext::class)->id());
            }
        });

        static::updating(function (Model $model): void {
            if (! $model->isDirty(WorkspaceScope::COLUMN)) {
                return;
            }

            /** @var int|null $original */
            $original = $model->getOriginal(WorkspaceScope::COLUMN);
            /** @var int|null $current */
            $current = $model->getAttribute(WorkspaceScope::COLUMN);

            throw WorkspaceMismatchException::cannotChangeWorkspace(
                $model::class,
                $original === null ? null : (int) $original,
                $current === null ? null : (int) $current,
            );
        });
    }

    /**
     * El identificador del workspace dueño de esta fila.
     */
    public function workspaceId(): int
    {
        return (int) $this->getAttribute(WorkspaceScope::COLUMN);
    }
}
