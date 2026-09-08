<?php

declare(strict_types=1);

namespace App\Modules\Content\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Registro del módulo Content (CMS). En sub-slices posteriores enlaza los
 * contratos de kernel (SectionDataResolver, DynamicRouteResolver) y registra
 * listeners (EntryPublished -> auditoría). Por ahora, andamio del módulo.
 */
final class ContentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        //
    }
}
