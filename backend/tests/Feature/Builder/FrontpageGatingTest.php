<?php

declare(strict_types=1);

use App\Modules\Billing\Infrastructure\Models\Capability;
use App\Modules\Billing\Infrastructure\Models\Plan;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(fn () => $this->seed(DatabaseSeeder::class));

/** @return array<string, mixed> */
function featuredSchema(): array
{
    return [
        'schema_version' => 1,
        'sections' => [[
            'id' => Str::upper((string) Str::ulid()),
            'type' => 'featured',
            'variant' => 'featured-list',
            'visible' => true,
            'props' => ['collection' => 'articles', 'items' => [], 'showExcerpt' => false, 'showImage' => false, 'showDate' => true],
            'settings' => ['spacing' => ['top' => 'md', 'bottom' => 'md']],
        ]],
    ];
}

it('guardar un schema con `featured` requiere publisher.frontpages (Pro)', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = cmsOwnerContext();
    $page = makePage($ws, $site, 'Portada', '/');
    Sanctum::actingAs($user);
    $url = pagesUrl($ws, $site)."/{$page->ulid}";

    // Pro incluye publisher.frontpages → guarda.
    $this->patchJson($url, ['schema' => featuredSchema()])->assertOk();

    // Sin la capability → 403 al guardar.
    Plan::where('key', 'pro')->firstOrFail()
        ->capabilities()->detach(Capability::where('key', 'publisher.frontpages')->firstOrFail()->id);

    $this->patchJson($url, ['schema' => featuredSchema()])->assertForbidden();
});

it('un schema sin `featured` no exige la capability', function () {
    ['user' => $user, 'ws' => $ws, 'site' => $site] = cmsOwnerContext();
    $page = makePage($ws, $site, 'Normal', '/normal');
    Plan::where('key', 'pro')->firstOrFail()
        ->capabilities()->detach(Capability::where('key', 'publisher.frontpages')->firstOrFail()->id);
    Sanctum::actingAs($user);

    $this->patchJson(pagesUrl($ws, $site)."/{$page->ulid}", ['schema' => heroSchema('Hola')])->assertOk();
});
