<?php

declare(strict_types=1);

use App\Modules\Seo\Infrastructure\Models\Redirect;
use App\Modules\Sites\Infrastructure\Models\Site;
use Illuminate\Database\QueryException;

it('crea un redirect con workspace/site, ulid y defaults', function () {
    [$ws, $site] = builderSite();

    withinWorkspace($ws, function () use ($site) {
        $redirect = Redirect::factory()->create([
            'site_id' => $site->id,
            'from_path' => '/viejo',
            'to_path' => '/nuevo',
        ]);

        expect($redirect->workspace_id)->not->toBeNull()
            ->and($redirect->site_id)->toBe($site->id)
            ->and($redirect->ulid)->not->toBeNull()
            ->and($redirect->status)->toBe(301)
            ->and($redirect->source)->toBe(Redirect::SOURCE_MANUAL)
            ->and($redirect->is_active)->toBeTrue();
    });
});

it('impide dos redirects con el mismo from_path en un sitio', function () {
    [$ws, $site] = builderSite();

    withinWorkspace($ws, function () use ($site) {
        Redirect::factory()->create(['site_id' => $site->id, 'from_path' => '/dup']);

        expect(fn () => Redirect::factory()->create(['site_id' => $site->id, 'from_path' => '/dup']))
            ->toThrow(QueryException::class);
    });
});

it('permite el mismo from_path en sitios distintos', function () {
    [$ws, $siteA] = builderSite();
    $siteB = withinWorkspace($ws, fn () => Site::factory()->create());

    withinWorkspace($ws, function () use ($siteA, $siteB) {
        Redirect::factory()->create(['site_id' => $siteA->id, 'from_path' => '/promo']);
        Redirect::factory()->create(['site_id' => $siteB->id, 'from_path' => '/promo']);

        expect(Redirect::forSite($siteA->id)->count())->toBe(1)
            ->and(Redirect::forSite($siteB->id)->count())->toBe(1);
    });
});

it('aísla los redirects entre workspaces (global scope)', function () {
    [$wsA, $siteA] = builderSite();
    [$wsB] = builderSite();

    withinWorkspace($wsA, fn () => Redirect::factory()->create(['site_id' => $siteA->id]));

    withinWorkspace($wsB, fn () => expect(Redirect::count())->toBe(0));
    withinWorkspace($wsA, fn () => expect(Redirect::count())->toBe(1));
});
