<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http\Middleware;

use App\Modules\Shared\Domain\Tenancy\WorkspaceContext;
use App\Modules\Tenancy\Infrastructure\Models\Workspace;
use App\Modules\Tenancy\Infrastructure\Models\WorkspaceMember;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resuelve el workspace activo a partir del segmento {workspace} de la ruta.
 *
 * El workspace_id JAMÁS se confía del cliente: aquí se valida que el usuario
 * autenticado sea miembro del workspace de la URL, y sólo entonces se fija el
 * contexto. Un no-miembro recibe 403 y el dominio nunca se ejecuta para él.
 */
final class ResolveWorkspace
{
    public function __construct(private readonly WorkspaceContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $param = $request->route('workspace');
        $workspace = $param instanceof Workspace ? $param : Workspace::findByUlid((string) $param);
        abort_if($workspace === null, 404, 'Workspace no encontrado.');

        // La membresía se consulta SIN el global scope: el contexto aún no existe.
        $membership = WorkspaceMember::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->first();

        abort_if($membership === null, 403, 'No perteneces a este workspace.');

        $this->context->set($workspace->id);

        $request->attributes->set('workspace', $workspace);
        $request->attributes->set('membership', $membership);

        return $next($request);
    }
}
