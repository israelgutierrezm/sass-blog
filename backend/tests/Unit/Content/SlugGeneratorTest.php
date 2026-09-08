<?php

declare(strict_types=1);

use App\Modules\Content\Application\SlugGenerator;

it('normaliza a slug y añade sufijos ante colisión', function () {
    $slugs = new SlugGenerator;

    expect($slugs->unique('Hola Mundo', fn () => false))->toBe('hola-mundo');

    $taken = ['articles'];
    expect($slugs->unique('Articles', fn (string $s) => in_array($s, $taken, true)))->toBe('articles-2');

    $taken = ['post', 'post-2'];
    expect($slugs->unique('Post', fn (string $s) => in_array($s, $taken, true)))->toBe('post-3');
});

it('usa el fallback cuando el origen no produce slug', function () {
    $slugs = new SlugGenerator;

    expect($slugs->unique('!!!', fn () => false, 'item'))->toBe('item');
});
