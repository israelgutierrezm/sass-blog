<?php

declare(strict_types=1);

use App\Modules\Content\Application\Rendering\BindingResolver;

it('resuelve bindings permitidos y deja en null los no permitidos', function () {
    $map = ['entry.title' => 'Hola', 'entry.data.body' => '<p>x</p>'];

    $schema = json_decode('{
        "heading": {"$bind": "entry.title"},
        "body": {"$bind": "entry.data.body"},
        "evil": {"$bind": "entry.author.password"},
        "system": {"$bind": "config.app.key"},
        "static": "literal",
        "nested": {"deep": {"$bind": "entry.title"}},
        "list": [{"$bind": "entry.title"}, "plain"]
    }');

    $out = BindingResolver::resolve($schema, $map);

    expect($out->heading)->toBe('Hola')
        ->and($out->body)->toBe('<p>x</p>')
        ->and($out->evil)->toBeNull()
        ->and($out->system)->toBeNull()
        ->and($out->static)->toBe('literal')
        ->and($out->nested->deep)->toBe('Hola')
        ->and($out->list[0])->toBe('Hola')
        ->and($out->list[1])->toBe('plain');
});

it('no trata como binding un objeto con $bind y claves extra (sólo el nodo puro)', function () {
    $map = ['entry.title' => 'Hola'];

    $node = json_decode('{"$bind": "entry.title", "extra": 1}');

    $out = BindingResolver::resolve($node, $map);

    // Se recorre como objeto normal: NO se resuelve a 'Hola'.
    expect($out)->toBeObject()
        ->and($out->{'$bind'})->toBe('entry.title')
        ->and($out->extra)->toBe(1);
});

it('deja intactos los valores escalares y arreglos sin bindings', function () {
    $out = BindingResolver::resolve(json_decode('{"a": 1, "b": ["x", "y"], "c": true}'), []);

    expect($out->a)->toBe(1)
        ->and($out->b)->toBe(['x', 'y'])
        ->and($out->c)->toBeTrue();
});
