<?php

declare(strict_types=1);

use App\Modules\Media\Application\Jobs\GenerateMediaVariants;
use App\Modules\Media\Application\StoreMediaAsset;
use App\Modules\Shared\Domain\Tenancy\WorkspaceContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('public'));

it('genera thumb/medium/large y marca el asset ready', function () {
    [$ws, $site] = builderSite();

    $asset = withinWorkspace($ws, fn () => app(StoreMediaAsset::class)
        ->handle($site, UploadedFile::fake()->image('grande.jpg', 1600, 900))
        ->fresh('variants'));

    expect($asset->status)->toBe('ready')
        ->and($asset->variants)->toHaveCount(3)
        ->and($asset->variants->pluck('variant')->sort()->values()->all())->toBe(['large', 'medium', 'thumb']);

    $thumb = $asset->variants->firstWhere('variant', 'thumb');
    expect($thumb->width)->toBe(320);
    Storage::disk('public')->assertExists($thumb->path);
});

it('es idempotente: re-ejecutar el job no duplica variantes', function () {
    [$ws, $site] = builderSite();

    $count = withinWorkspace($ws, function () use ($site) {
        $asset = app(StoreMediaAsset::class)->handle($site, UploadedFile::fake()->image('grande.jpg', 1600, 900));
        // El job ya corrió (cola sync) durante la subida; re-ejecutarlo a mano:
        (new GenerateMediaVariants($asset->id, (int) $asset->workspace_id))->handle(app(WorkspaceContext::class));

        return $asset->variants()->count();
    });

    expect($count)->toBe(3);
});

it('no agranda: una imagen pequeña sólo genera las variantes menores al original', function () {
    [$ws, $site] = builderSite();

    $asset = withinWorkspace($ws, fn () => app(StoreMediaAsset::class)
        ->handle($site, UploadedFile::fake()->image('chica.jpg', 400, 300))
        ->fresh('variants'));

    // thumb (320<400) sí; medium (768) y large (1440) se saltan.
    expect($asset->variants)->toHaveCount(1)
        ->and($asset->variants->first()->variant)->toBe('thumb')
        ->and($asset->variants->first()->width)->toBe(320);
});

it('un no-raster (svg) queda ready sin variantes', function () {
    [$ws, $site] = builderSite();

    $asset = withinWorkspace($ws, fn () => app(StoreMediaAsset::class)
        ->handle($site, UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'))
        ->fresh('variants'));

    expect($asset->status)->toBe('ready')
        ->and($asset->variants)->toHaveCount(0);
});
