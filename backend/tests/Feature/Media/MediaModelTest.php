<?php

declare(strict_types=1);

use App\Modules\Media\Infrastructure\Models\MediaAsset;
use App\Modules\Media\Infrastructure\Models\MediaVariant;
use App\Modules\Sites\Infrastructure\Models\Site;
use Illuminate\Database\QueryException;

it('crea un asset con workspace/site, status y detección de imagen', function () {
    [$ws, $site] = builderSite();

    withinWorkspace($ws, function () use ($site) {
        $asset = MediaAsset::factory()->create(['site_id' => $site->id, 'mime_type' => 'image/png']);

        expect($asset->status)->toBe('ready')
            ->and($asset->isImage())->toBeTrue()
            ->and($asset->workspace_id)->not->toBeNull()
            ->and($asset->site_id)->toBe($site->id);
    });
});

it('impide duplicar un binario en el mismo sitio (dedup por checksum)', function () {
    [$ws, $site] = builderSite();

    withinWorkspace($ws, function () use ($site) {
        $checksum = hash('sha256', 'mismo-binario');
        MediaAsset::factory()->create(['site_id' => $site->id, 'checksum' => $checksum]);

        expect(fn () => MediaAsset::factory()->create(['site_id' => $site->id, 'checksum' => $checksum]))
            ->toThrow(QueryException::class);
    });
});

it('un asset agrupa sus variantes', function () {
    [$ws, $site] = builderSite();

    withinWorkspace($ws, function () use ($site) {
        $asset = MediaAsset::factory()->create(['site_id' => $site->id]);
        foreach ([MediaVariant::VARIANT_THUMB, MediaVariant::VARIANT_MEDIUM, MediaVariant::VARIANT_LARGE] as $variant) {
            MediaVariant::factory()->create([
                'site_id' => $site->id,
                'media_asset_id' => $asset->id,
                'variant' => $variant,
            ]);
        }

        expect($asset->variants()->count())->toBe(3);
    });
});

it('aísla los assets por sitio', function () {
    [$ws, $siteA] = builderSite();
    $siteB = withinWorkspace($ws, fn () => Site::factory()->create());

    withinWorkspace($ws, function () use ($siteA, $siteB) {
        MediaAsset::factory()->create(['site_id' => $siteA->id]);
        MediaAsset::factory()->create(['site_id' => $siteB->id]);

        expect(MediaAsset::forSite($siteA->id)->count())->toBe(1)
            ->and(MediaAsset::forSite($siteB->id)->count())->toBe(1);
    });
});
