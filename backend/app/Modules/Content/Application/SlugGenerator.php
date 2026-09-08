<?php

declare(strict_types=1);

namespace App\Modules\Content\Application;

use Illuminate\Support\Str;

/**
 * Genera slugs únicos. El llamador decide el scope de unicidad mediante el callback
 * $exists (recibe un slug candidato y devuelve true si ya está tomado). Ante
 * colisión, añade sufijos -2, -3, … Usado por colecciones, entries, autores y
 * categorías (cada uno con su propio scope de unicidad).
 */
final class SlugGenerator
{
    /**
     * @param  callable(string): bool  $exists
     */
    public function unique(string $source, callable $exists, string $fallback = 'item'): string
    {
        $base = Str::slug($source) ?: $fallback;
        $slug = $base;
        $suffix = 1;

        while ($exists($slug)) {
            $suffix++;
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }
}
