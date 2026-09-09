<?php

declare(strict_types=1);

namespace App\Modules\Media\Providers;

use App\Modules\Media\Infrastructure\Models\MediaAsset;
use App\Modules\Media\Policies\MediaPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Registro del módulo Media (librería de assets por-sitio, ADR-016). Registra la
 * Policy; en sub-slices posteriores, el listener de limpieza de binarios.
 */
final class MediaServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(MediaAsset::class, MediaPolicy::class);
    }
}
