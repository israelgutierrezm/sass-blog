<?php

declare(strict_types=1);

use App\Modules\Content\Domain\Fields\FieldType;

it('el enum FieldType coincide con el catálogo compartido (conformidad TS<->PHP)', function () {
    $path = resource_path('site-schema/field-types.v1.json');
    expect(is_file($path))->toBeTrue();

    /** @var array{types: list<array{key: string}>} $json */
    $json = json_decode((string) file_get_contents($path), true);

    $artifactKeys = collect($json['types'])->pluck('key')->sort()->values()->all();
    $enumKeys = collect(FieldType::cases())->map(fn (FieldType $c) => $c->value)->sort()->values()->all();

    expect($enumKeys)->toBe($artifactKeys);
});
