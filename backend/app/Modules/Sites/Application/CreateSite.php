<?php

declare(strict_types=1);

namespace App\Modules\Sites\Application;

use App\Modules\Sites\Events\SiteCreated;
use App\Modules\Sites\Infrastructure\Models\Site;
use Illuminate\Support\Facades\DB;

/**
 * Crea un site y emite SiteCreated para que otros módulos reaccionen (Content
 * siembra el preset de artículos). El workspace_id lo rellena BelongsToWorkspace
 * desde el contexto activo: nunca llega del cliente.
 */
final class CreateSite
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes): Site
    {
        return DB::transaction(function () use ($attributes): Site {
            $site = Site::create($attributes);

            event(new SiteCreated((int) $site->workspace_id, $site->id));

            return $site;
        });
    }
}
