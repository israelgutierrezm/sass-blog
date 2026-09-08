<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope de workspace. El mecanismo central de aislamiento (ADR-001).
 *
 * Todo modelo de dominio scopeado lo lleva, y el test estructural
 * tests/Architecture/WorkspaceScopeTest.php falla si a alguno le falta.
 *
 * Dos detalles que no son accidentales:
 *
 * 1. Lanza excepción si no hay contexto (vía WorkspaceContext::id()), en vez de
 *    filtrar por un workspace inexistente o devolver vacío.
 * 2. Califica la columna con el nombre de la tabla: sin qualifyColumn, un join
 *    entre dos tablas que ambas tienen workspace_id produce "column is ambiguous".
 */
final class WorkspaceScope implements Scope
{
    /**
     * Nombre de la columna de workspace. Igual en toda tabla de dominio scopeada.
     */
    public const COLUMN = 'workspace_id';

    public function __construct(private readonly WorkspaceContext $context) {}

    public function apply(Builder $builder, Model $model): void
    {
        $builder->where(
            $model->qualifyColumn(self::COLUMN),
            '=',
            $this->context->id(),
        );
    }
}
