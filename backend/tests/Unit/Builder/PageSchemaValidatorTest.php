<?php

declare(strict_types=1);

use App\Modules\Builder\Application\Schema\PageSchemaValidator;

function heroSection(string $id = '01ARZ3NDEKTSV4RRFFQ69G5FAV'): array
{
    return [
        'id' => $id,
        'type' => 'hero',
        'variant' => 'hero-centered',
        'visible' => true,
        'props' => ['heading' => 'Hola'],
        'settings' => ['spacing' => ['top' => 'md', 'bottom' => 'md']],
    ];
}

/** @return list<array{path: string, message: string}> */
function schemaErrors(array $schema, string $profile = 'draft'): array
{
    return app(PageSchemaValidator::class)->validateJson((string) json_encode($schema), $profile);
}

it('acepta un draft válido con una sección hero', function () {
    expect(schemaErrors(['schema_version' => 1, 'sections' => [heroSection()]]))->toBe([]);
});

it('acepta draft vacío pero rechaza publish vacío', function () {
    expect(schemaErrors(['schema_version' => 1, 'sections' => []], 'draft'))->toBe([]);
    expect(schemaErrors(['schema_version' => 1, 'sections' => []], 'publish'))->not->toBe([]);
});

it('rechaza un tipo desconocido', function () {
    $section = heroSection();
    $section['type'] = 'carousel';
    expect(schemaErrors(['schema_version' => 1, 'sections' => [$section]]))->not->toBe([]);
});

it('rechaza heading requerido faltante', function () {
    $section = heroSection();
    $section['props'] = ['align' => 'center'];
    expect(schemaErrors(['schema_version' => 1, 'sections' => [$section]]))->not->toBe([]);
});

it('rechaza claves extra (additionalProperties:false)', function () {
    $section = heroSection();
    $section['extra'] = true;
    expect(schemaErrors(['schema_version' => 1, 'sections' => [$section]]))->not->toBe([]);
});

it('rechaza un id que no es ULID', function () {
    expect(schemaErrors(['schema_version' => 1, 'sections' => [heroSection('no-ulid')]]))->not->toBe([]);
});

it('rechaza ids de sección duplicados', function () {
    $errors = schemaErrors(['schema_version' => 1, 'sections' => [heroSection(), heroSection()]]);
    expect($errors)->not->toBe([])
        ->and(collect($errors)->contains(fn ($e) => str_contains($e['message'], 'duplicado')))->toBeTrue();
});
