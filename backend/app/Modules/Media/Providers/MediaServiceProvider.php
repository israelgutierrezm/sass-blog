<?php

declare(strict_types=1);

namespace App\Modules\Media\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Registro del módulo Media (librería de assets por-sitio, ADR-016). En sub-slices
 * posteriores registra Policies, el binding de capability y el listener de limpieza
 * de binarios. Por ahora, andamio del módulo.
 */
final class MediaServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        //
    }
}
