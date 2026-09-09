<?php

declare(strict_types=1);

use App\Modules\Media\Application\StoreMediaAsset;
use App\Modules\Media\Http\Resources\MediaAssetResource;
use App\Modules\Media\Infrastructure\Models\MediaAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('public'));

it('sube una imagen: guarda el binario, lee dimensiones y crea el asset', function () {
    [$ws, $site] = builderSite();

    $asset = withinWorkspace($ws, fn () => app(StoreMediaAsset::class)
        ->handle($site, UploadedFile::fake()->image('foto.jpg', 800, 600)));

    expect($asset->status)->toBe('ready')
        ->and($asset->width)->toBe(800)
        ->and($asset->height)->toBe(600)
        ->and($asset->mime_type)->toContain('image')
        ->and($asset->checksum)->toHaveLength(64);

    Storage::disk('public')->assertExists($asset->path);
});

it('deduplica: subir el mismo binario devuelve el mismo asset', function () {
    [$ws, $site] = builderSite();

    [$a, $b, $count] = withinWorkspace($ws, function () use ($site) {
        $service = app(StoreMediaAsset::class);
        $a = $service->handle($site, UploadedFile::fake()->createWithContent('a.pdf', 'BYTES-IDENTICOS'));
        $b = $service->handle($site, UploadedFile::fake()->createWithContent('a.pdf', 'BYTES-IDENTICOS'));

        return [$a, $b, MediaAsset::count()];
    });

    expect($b->id)->toBe($a->id)
        ->and($count)->toBe(1);
});

it('restaura un asset borrado al re-subir el mismo binario', function () {
    [$ws, $site] = builderSite();

    $result = withinWorkspace($ws, function () use ($site) {
        $service = app(StoreMediaAsset::class);
        $first = $service->handle($site, UploadedFile::fake()->createWithContent('a.pdf', 'REUP'));
        $first->delete();
        $second = $service->handle($site, UploadedFile::fake()->createWithContent('a.pdf', 'REUP'));

        return ['firstId' => $first->id, 'second' => $second];
    });

    expect($result['second']->id)->toBe($result['firstId'])
        ->and($result['second']->trashed())->toBeFalse();
});

it('un archivo no-imagen no tiene dimensiones', function () {
    [$ws, $site] = builderSite();

    $asset = withinWorkspace($ws, fn () => app(StoreMediaAsset::class)
        ->handle($site, UploadedFile::fake()->createWithContent('doc.pdf', '%PDF-1.4 contenido')));

    expect($asset->width)->toBeNull()
        ->and($asset->height)->toBeNull();
});

it('el Resource expone url pública y oculta path/disk internos', function () {
    [$ws, $site] = builderSite();

    $asset = withinWorkspace($ws, fn () => app(StoreMediaAsset::class)
        ->handle($site, UploadedFile::fake()->image('x.png', 100, 100)));

    $array = (new MediaAssetResource($asset))->toArray(request());

    expect($array)->toHaveKey('url')
        ->and($array['url'])->toBeString()
        ->and($array)->not->toHaveKey('path')
        ->and($array)->not->toHaveKey('disk');
});
