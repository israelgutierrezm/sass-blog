<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Billing\Infrastructure\Models\Plan;
use App\Modules\Builder\Application\CreatePage;
use App\Modules\Builder\Application\PublishPage;
use App\Modules\Builder\Application\SaveDraft;
use App\Modules\Builder\Infrastructure\Models\Page;
use App\Modules\Content\Infrastructure\Models\Collection;
use App\Modules\Identity\Application\RegisterUser;
use App\Modules\Shared\Domain\Tenancy\WorkspaceContext;
use App\Modules\Sites\Application\CreateSite;
use App\Modules\Sites\Infrastructure\Models\Site;
use App\Modules\Tenancy\Infrastructure\Models\Workspace;
use App\Modules\Tenancy\Infrastructure\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Configuración base de Pest
|--------------------------------------------------------------------------
|
| Suites: Unit (dominio puro) · Feature (endpoints e integración) ·
| Architecture (reglas estructurales). Feature/Architecture corren contra
| MySQL 8 real (base sass_blog_testing), no SQLite (ADR-001 / phpunit.xml).
|
*/

pest()->extend(TestCase::class)->in('Unit', 'Feature', 'Architecture');
pest()->use(RefreshDatabase::class)->in('Feature', 'Architecture');

/*
|--------------------------------------------------------------------------
| Helpers de contexto de workspace
|--------------------------------------------------------------------------
*/

function actingForWorkspace(Workspace|int $workspace): int
{
    $id = $workspace instanceof Workspace ? $workspace->id : $workspace;
    app(WorkspaceContext::class)->set($id);

    return $id;
}

function withinWorkspace(Workspace|int $workspace, Closure $callback): mixed
{
    $id = $workspace instanceof Workspace ? $workspace->id : $workspace;

    return app(WorkspaceContext::class)->runFor($id, $callback);
}

/*
|--------------------------------------------------------------------------
| Helpers de dominio compartidos (viven aquí para estar disponibles en
| cualquier suite, incluso al correr un subconjunto de archivos)
|--------------------------------------------------------------------------
*/

/**
 * Registra un usuario con su workspace personal, rol owner y suscripción free.
 *
 * @return array{user: User, workspace: Workspace}
 */
function registered(string $email = 'user@example.com'): array
{
    return app(RegisterUser::class)->handle('Persona', $email, 'Password!123');
}

/**
 * Crea un workspace con un site dentro (contexto ya restaurado a null al salir).
 *
 * @return array{0: Workspace, 1: Site}
 */
function builderSite(): array
{
    $ws = Workspace::factory()->create(['owner_id' => User::factory()->create()->id]);
    $site = withinWorkspace($ws, fn () => Site::factory()->create());

    return [$ws, $site];
}

/**
 * Owner registrado + un site en su workspace.
 *
 * @return array{user: User, ws: Workspace, site: Site}
 */
function ownerWithSite(string $email = 'owner@example.com'): array
{
    ['user' => $user, 'workspace' => $ws] = registered($email);
    $site = withinWorkspace($ws, fn () => Site::factory()->create());

    return ['user' => $user, 'ws' => $ws, 'site' => $site];
}

function makePage(Workspace $ws, Site $site, string $title = 'Home', string $path = '/'): Page
{
    return withinWorkspace($ws, fn () => app(CreatePage::class)->handle($site, $title, $path));
}

/** Crea, escribe y publica una página; devuelve el modelo publicado. */
function publishedPage(Workspace $ws, Site $site, string $path = '/', string $heading = 'Público'): Page
{
    $page = makePage($ws, $site, 'Home', $path);

    return withinWorkspace($ws, function () use ($page, $heading) {
        app(SaveDraft::class)->handle($page, heroSchema($heading));

        return app(PublishPage::class)->handle($page->fresh());
    });
}

/**
 * Crea y publica un artículo vía API (requiere actingAs previo); devuelve [ulid, slug].
 *
 * @param  array<string, mixed>  $values
 * @return array{0: string, 1: string}
 */
function publishedArticle(string $wsUlid, string $siteUlid, string $collectionUlid, array $values = []): array
{
    $base = "/api/v1/workspaces/{$wsUlid}/sites/{$siteUlid}/collections/{$collectionUlid}/entries";
    $created = test()->postJson($base, [
        'title' => 'Mi Artículo',
        'values' => array_merge(['excerpt' => 'Un resumen', 'body' => '<p>Cuerpo</p>'], $values),
    ])->json('data');
    test()->postJson("{$base}/{$created['id']}/publish")->assertOk();

    return [$created['id'], $created['slug']];
}

/**
 * Owner con plan Pro (capability cms.* habilitada) + un site con el preset de
 * artículos ya sembrado. Requiere haber corrido DatabaseSeeder (plan pro + caps).
 *
 * @return array{user: User, ws: Workspace, site: Site, articles: Collection}
 */
function cmsOwnerContext(string $email = 'owner@example.com'): array
{
    ['user' => $user, 'workspace' => $ws] = registered($email);

    $pro = Plan::where('key', 'pro')->firstOrFail();
    withinWorkspace($ws, fn () => $ws->subscription()->update(['plan_id' => $pro->id]));

    $site = withinWorkspace($ws, fn () => app(CreateSite::class)->handle([
        'name' => 'Blog', 'slug' => 'blog',
    ]));

    $articles = withinWorkspace($ws, fn () => Collection::query()
        ->where('site_id', $site->id)->where('handle', 'articles')->firstOrFail());

    return ['user' => $user, 'ws' => $ws, 'site' => $site, 'articles' => $articles];
}

function memberWithRole(Workspace $ws, string $role): User
{
    $user = User::factory()->create();
    withinWorkspace($ws, function () use ($ws, $user, $role): void {
        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id' => $user->id,
            'role' => $role,
            'joined_at' => now(),
        ]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($ws->id);
        $user->assignRole($role);
    });

    return $user;
}

function pagesUrl(Workspace $ws, Site $site): string
{
    return "/api/v1/workspaces/{$ws->ulid}/sites/{$site->ulid}/pages";
}

function menusUrl(string $wsUlid, string $siteUlid): string
{
    return "/api/v1/workspaces/{$wsUlid}/sites/{$siteUlid}/menus";
}

/**
 * Page schema válido con una sección hero. `settings` NO vacío a propósito: PHP no
 * distingue {} de [], así que un settings vacío se serializaría como [] y opis lo
 * rechazaría como "no es objeto".
 *
 * @return array<string, mixed>
 */
function heroSchema(string $heading = 'Hola Mundo'): array
{
    return [
        'schema_version' => 1,
        'sections' => [[
            'id' => Str::upper((string) Str::ulid()),
            'type' => 'hero',
            'variant' => 'hero-centered',
            'visible' => true,
            'props' => ['heading' => $heading],
            'settings' => ['spacing' => ['top' => 'md', 'bottom' => 'md']],
        ]],
    ];
}
