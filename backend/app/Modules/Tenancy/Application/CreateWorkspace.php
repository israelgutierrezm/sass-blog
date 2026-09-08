<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application;

use App\Models\User;
use App\Modules\Shared\Domain\Tenancy\WorkspaceContext;
use App\Modules\Tenancy\Events\WorkspaceCreated;
use App\Modules\Tenancy\Infrastructure\Models\Workspace;
use App\Modules\Tenancy\Infrastructure\Models\WorkspaceMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Crea un workspace con su dueño como primer miembro (rol owner) y emite
 * WorkspaceCreated para que otros módulos reaccionen (RBAC, suscripción).
 *
 * Todo en una transacción; el evento se emite DENTRO del contexto del nuevo
 * workspace para que los listeners tengan workspace resuelto.
 */
final class CreateWorkspace
{
    public function __construct(private readonly WorkspaceContext $context) {}

    public function handle(User $owner, string $name, bool $personal = false): Workspace
    {
        return DB::transaction(function () use ($owner, $name, $personal): Workspace {
            $workspace = Workspace::create([
                'name' => $name,
                'slug' => $this->uniqueSlug($name),
                'owner_id' => $owner->id,
                'personal' => $personal,
            ]);

            $this->context->runFor($workspace->id, function () use ($workspace, $owner): void {
                WorkspaceMember::create([
                    'workspace_id' => $workspace->id,
                    'user_id' => $owner->id,
                    'role' => 'owner',
                    'joined_at' => now(),
                ]);

                event(new WorkspaceCreated($workspace->id, $owner->id));
            });

            return $workspace;
        });
    }

    private function uniqueSlug(string $base): string
    {
        $slug = Str::slug($base) ?: 'workspace';
        $candidate = $slug;
        $i = 1;

        while (Workspace::withTrashed()->where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.(++$i);
        }

        return $candidate;
    }
}
