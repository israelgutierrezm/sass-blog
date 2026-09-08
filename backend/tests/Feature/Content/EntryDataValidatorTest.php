<?php

declare(strict_types=1);

use App\Modules\Content\Application\Validation\EntryDataValidator;
use App\Modules\Content\Domain\Fields\FieldType;
use App\Modules\Content\Infrastructure\Models\Collection;
use App\Modules\Content\Infrastructure\Models\CollectionField;
use App\Modules\Content\Infrastructure\Models\Entry;
use App\Modules\Sites\Infrastructure\Models\Site;

/** Colección con un set representativo de campos para ejercitar el validador. */
function collectionWithFields(Site $site): Collection
{
    $collection = Collection::factory()->create(['site_id' => $site->id]);

    $definitions = [
        ['key' => 'excerpt', 'type' => FieldType::Textarea, 'required' => false],
        ['key' => 'body', 'type' => FieldType::RichText, 'required' => true],
        ['key' => 'featured', 'type' => FieldType::Boolean, 'required' => false],
        ['key' => 'hero', 'type' => FieldType::Url, 'required' => false],
        ['key' => 'level', 'type' => FieldType::Select, 'required' => false, 'config' => ['options' => ['beginner', 'advanced']]],
        ['key' => 'tags', 'type' => FieldType::MultiSelect, 'required' => false, 'config' => ['options' => ['a', 'b', 'c']]],
        ['key' => 'cover', 'type' => FieldType::Media, 'required' => false],
        ['key' => 'gallery', 'type' => FieldType::MediaArray, 'required' => false],
        ['key' => 'when', 'type' => FieldType::Date, 'required' => false],
        ['key' => 'at', 'type' => FieldType::DateTime, 'required' => false],
        ['key' => 'price', 'type' => FieldType::Money, 'required' => false],
        // Claves adversas: con punto (evadía la notación de puntos de Laravel) y numérica.
        ['key' => 'seo.canonical', 'type' => FieldType::Url, 'required' => false],
        ['key' => '2024', 'type' => FieldType::Text, 'required' => false],
    ];

    foreach ($definitions as $position => $definition) {
        CollectionField::factory()->create(array_merge($definition, [
            'site_id' => $site->id,
            'collection_id' => $collection->id,
            'label' => ucfirst($definition['key']),
            'position' => $position,
        ]));
    }

    return $collection->load('fields');
}

function validateEntry(Collection $collection, array $data, string $profile = 'draft'): array
{
    return app(EntryDataValidator::class)->validate($collection->fields, $data, $profile);
}

it('en draft acepta datos parciales sin exigir los required', function () {
    [$ws, $site] = builderSite();

    withinWorkspace($ws, function () use ($site) {
        $collection = collectionWithFields($site);

        expect(validateEntry($collection, ['excerpt' => 'Resumen']))->toBe([]);
    });
});

it('en publish exige los campos required', function () {
    [$ws, $site] = builderSite();

    withinWorkspace($ws, function () use ($site) {
        $collection = collectionWithFields($site);

        $errors = validateEntry($collection, ['excerpt' => 'Resumen'], 'publish');

        expect($errors)->not->toBe([])
            ->and(collect($errors)->pluck('path'))->toContain('body');
    });
});

it('en publish acepta cuando el required está presente', function () {
    [$ws, $site] = builderSite();

    withinWorkspace($ws, function () use ($site) {
        $collection = collectionWithFields($site);

        expect(validateEntry($collection, ['body' => '<p>Contenido</p>'], 'publish'))->toBe([]);
    });
});

it('rechaza valores del tipo equivocado', function () {
    [$ws, $site] = builderSite();

    withinWorkspace($ws, function () use ($site) {
        $collection = collectionWithFields($site);

        expect(validateEntry($collection, ['featured' => 'quizá']))->not->toBe([]);
        expect(validateEntry($collection, ['featured' => true]))->toBe([]);
    });
});

it('rechaza URLs con esquema peligroso y acepta http/https', function () {
    [$ws, $site] = builderSite();

    withinWorkspace($ws, function () use ($site) {
        $collection = collectionWithFields($site);

        expect(validateEntry($collection, ['hero' => 'javascript:alert(1)']))->not->toBe([]);
        expect(validateEntry($collection, ['hero' => 'https://cdn.example.com/x.png']))->toBe([]);
    });
});

it('select y multiselect sólo aceptan opciones declaradas', function () {
    [$ws, $site] = builderSite();

    withinWorkspace($ws, function () use ($site) {
        $collection = collectionWithFields($site);

        expect(validateEntry($collection, ['level' => 'expert']))->not->toBe([]);
        expect(validateEntry($collection, ['level' => 'beginner']))->toBe([]);

        expect(validateEntry($collection, ['tags' => ['a', 'z']]))->not->toBe([]);
        expect(validateEntry($collection, ['tags' => ['a', 'b']]))->toBe([]);
    });
});

it('rechaza claves no declaradas por la colección', function () {
    [$ws, $site] = builderSite();

    withinWorkspace($ws, function () use ($site) {
        $collection = collectionWithFields($site);

        $errors = validateEntry($collection, ['fantasma' => 1]);

        expect($errors)->not->toBe([])
            ->and($errors[0]['message'])->toContain('no está declarado');
    });
});

it('una key con punto NO evade la validación (acceso literal, no notación de puntos)', function () {
    [$ws, $site] = builderSite();

    withinWorkspace($ws, function () use ($site) {
        $collection = collectionWithFields($site);

        // El campo 'seo.canonical' es Url: SafeUrl debe correr sobre el valor plano.
        expect(validateEntry($collection, ['seo.canonical' => 'javascript:alert(1)']))->not->toBe([]);
        expect(validateEntry($collection, ['seo.canonical' => 'https://example.com/x']))->toBe([]);
    });
});

it('acepta un campo declarado con key puramente numérica', function () {
    [$ws, $site] = builderSite();

    withinWorkspace($ws, function () use ($site) {
        $collection = collectionWithFields($site);

        // PHP normaliza la key "2024" a int; la detección de claves fantasma no debe
        // marcarla como no declarada.
        expect(validateEntry($collection, ['2024' => 'Retrospectiva']))->toBe([]);
    });
});

it('en draft trata la cadena vacía como ausente en cualquier tipo escalar', function () {
    [$ws, $site] = builderSite();

    withinWorkspace($ws, function () use ($site) {
        $collection = collectionWithFields($site);

        expect(validateEntry($collection, ['hero' => '']))->toBe([]);
        expect(validateEntry($collection, ['level' => '']))->toBe([]);
        expect(validateEntry($collection, ['excerpt' => '']))->toBe([]);
    });
});

it('date exige Y-m-d y datetime exige ISO-8601 (rechaza relativos y granularidad cruzada)', function () {
    [$ws, $site] = builderSite();

    withinWorkspace($ws, function () use ($site) {
        $collection = collectionWithFields($site);

        // Date
        expect(validateEntry($collection, ['when' => '2026-05-01']))->toBe([]);
        expect(validateEntry($collection, ['when' => 'tomorrow']))->not->toBe([]);
        expect(validateEntry($collection, ['when' => '2026-05-01 13:45:00']))->not->toBe([]);

        // DateTime
        expect(validateEntry($collection, ['at' => '2026-05-01T13:45:00+00:00']))->toBe([]);
        expect(validateEntry($collection, ['at' => '2026-05-01']))->not->toBe([]);
        expect(validateEntry($collection, ['at' => 'now']))->not->toBe([]);
    });
});

it('money acota la escala a 2 decimales y rechaza notación científica', function () {
    [$ws, $site] = builderSite();

    withinWorkspace($ws, function () use ($site) {
        $collection = collectionWithFields($site);

        expect(validateEntry($collection, ['price' => 3.14]))->toBe([]);
        expect(validateEntry($collection, ['price' => 10]))->toBe([]);
        expect(validateEntry($collection, ['price' => 3.14159]))->not->toBe([]);
        expect(validateEntry($collection, ['price' => '1e3']))->not->toBe([]);
    });
});

it('media y media_array bloquean esquemas peligrosos (regresión de SafeUrl)', function () {
    [$ws, $site] = builderSite();

    withinWorkspace($ws, function () use ($site) {
        $collection = collectionWithFields($site);

        // Media escalar
        expect(validateEntry($collection, ['cover' => 'data:text/html,<script>']))->not->toBe([]);
        expect(validateEntry($collection, ['cover' => 'javascript:alert(1)']))->not->toBe([]);
        expect(validateEntry($collection, ['cover' => 'https://cdn.example.com/x.png']))->toBe([]);

        // Media array (regla de items .*)
        expect(validateEntry($collection, ['gallery' => ['javascript:alert(1)']]))->not->toBe([]);
        expect(validateEntry($collection, ['gallery' => ['https://cdn.example.com/a.png']]))->toBe([]);
    });
});

it('valida relaciones contra entries de la colección referida (mismo sitio)', function () {
    [$ws, $site] = builderSite();

    withinWorkspace($ws, function () use ($site) {
        $topics = Collection::factory()->create(['site_id' => $site->id]);
        $topic = Entry::factory()->create(['site_id' => $site->id, 'collection_id' => $topics->id]);

        $otherCollection = Collection::factory()->create(['site_id' => $site->id]);
        $otherEntry = Entry::factory()->create(['site_id' => $site->id, 'collection_id' => $otherCollection->id]);

        $collection = Collection::factory()->create(['site_id' => $site->id]);
        CollectionField::factory()->create([
            'site_id' => $site->id,
            'collection_id' => $collection->id,
            'key' => 'topic',
            'label' => 'Tema',
            'type' => FieldType::Relation->value,
            'position' => 0,
            'related_collection_id' => $topics->id,
        ]);
        $collection->load('fields');

        expect(validateEntry($collection, ['topic' => $topic->ulid]))->toBe([]);
        expect(validateEntry($collection, ['topic' => $otherEntry->ulid]))->not->toBe([]);
        expect(validateEntry($collection, ['topic' => 'NONEXISTENTULID0000000000']))->not->toBe([]);
    });
});
