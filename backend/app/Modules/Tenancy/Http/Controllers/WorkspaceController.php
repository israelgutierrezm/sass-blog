<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Application\CreateWorkspace;
use App\Modules\Tenancy\Http\Requests\StoreWorkspaceRequest;
use App\Modules\Tenancy\Http\Resources\WorkspaceResource;
use App\Modules\Tenancy\Infrastructure\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class WorkspaceController extends Controller
{
    /**
     * Workspaces a los que pertenece el usuario. No requiere contexto: es el paso
     * previo a elegir uno (flujo de identidad).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return WorkspaceResource::collection(
            $request->user()->workspaces()->get()
        );
    }

    public function store(StoreWorkspaceRequest $request, CreateWorkspace $create): JsonResponse
    {
        $workspace = $create->handle(
            $request->user(),
            $request->string('name')->toString(),
            personal: false,
        );

        return (new WorkspaceResource($workspace))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * La membresía la garantiza el middleware 'workspace'.
     */
    public function show(Workspace $workspace): WorkspaceResource
    {
        return new WorkspaceResource($workspace);
    }
}
