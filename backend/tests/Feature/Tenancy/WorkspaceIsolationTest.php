<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Shared\Domain\Tenancy\Exceptions\MissingWorkspaceContextException;
use App\Modules\Shared\Domain\Tenancy\Exceptions\WorkspaceMismatchException;
use App\Modules\Shared\Domain\Tenancy\WorkspaceContext;
use App\Modules\Sites\Infrastructure\Models\Site;
use App\Modules\Tenancy\Infrastructure\Models\Workspace;
use Illuminate\Database\QueryException;

function makeWorkspace(): Workspace
{
    return Workspace::factory()->create(['owner_id' => User::factory()->create()->id]);
}

it('aísla los sites por workspace (lecturas)', function () {
    $w1 = makeWorkspace();
    $w2 = makeWorkspace();

    withinWorkspace($w1, fn () => Site::factory()->create(['slug' => 'a']));
    withinWorkspace($w2, fn () => Site::factory()->create(['slug' => 'b']));

    actingForWorkspace($w1);
    expect(Site::count())->toBe(1);
    expect(Site::first()->slug)->toBe('a');

    actingForWorkspace($w2);
    expect(Site::count())->toBe(1);
    expect(Site::first()->slug)->toBe('b');
});

it('rellena workspace_id y genera ulid público al crear', function () {
    $w = makeWorkspace();
    actingForWorkspace($w);

    $site = Site::factory()->create(['slug' => 'x']);

    expect($site->workspace_id)->toBe($w->id)
        ->and($site->ulid)->toHaveLength(26)
        ->and($site->ulid)->toBe(strtoupper($site->ulid))
        ->and($site->getRouteKeyName())->toBe('ulid');
});

it('el dominio falla ruidosamente sin contexto de workspace', function () {
    app(WorkspaceContext::class)->forget();

    expect(fn () => Site::count())->toThrow(MissingWorkspaceContextException::class);
});

it('bloquea trasladar una fila a otro workspace', function () {
    $w1 = makeWorkspace();
    $w2 = makeWorkspace();

    $site = withinWorkspace($w1, fn () => Site::factory()->create(['slug' => 'x']));

    actingForWorkspace($w1);
    $site->workspace_id = $w2->id;

    expect(fn () => $site->save())->toThrow(WorkspaceMismatchException::class);
});

it('exige slug único dentro del workspace', function () {
    $w = makeWorkspace();
    actingForWorkspace($w);

    Site::factory()->create(['slug' => 'dup']);

    expect(fn () => Site::factory()->create(['slug' => 'dup']))
        ->toThrow(QueryException::class);
});

it('permite el mismo slug en workspaces distintos', function () {
    $w1 = makeWorkspace();
    $w2 = makeWorkspace();

    withinWorkspace($w1, fn () => Site::factory()->create(['slug' => 'home']));
    withinWorkspace($w2, fn () => Site::factory()->create(['slug' => 'home']));

    actingForWorkspace($w1);
    expect(Site::where('slug', 'home')->count())->toBe(1);
    actingForWorkspace($w2);
    expect(Site::where('slug', 'home')->count())->toBe(1);
});
