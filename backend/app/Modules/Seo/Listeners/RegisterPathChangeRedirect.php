<?php

declare(strict_types=1);

namespace App\Modules\Seo\Listeners;

use App\Modules\Seo\Infrastructure\Models\Redirect;
use App\Modules\Shared\Domain\Rendering\PublicPathChanged;

/**
 * Escucha PublicPathChanged (kernel) y auto-crea/actualiza el redirect `slug_change`
 * old→new (ADR-018). Corre dentro del WorkspaceContext del request que originó el
 * cambio, así que la escritura queda scopeada por tenant y `workspace_id` se autocompleta.
 */
final class RegisterPathChangeRedirect
{
    public function handle(PublicPathChanged $event): void
    {
        if ($event->oldPath === $event->newPath) {
            return;
        }

        // La ruta NUEVA vuelve a estar viva: cualquier auto-redirect que salía DE ella
        // quedó obsoleto (round-trip A→B→A). Se limpia para no formar un ciclo. No se
        // tocan los redirects MANUALES: son intención explícita del usuario.
        Redirect::query()
            ->where('site_id', $event->siteId)
            ->where('from_path', $event->newPath)
            ->where('source', Redirect::SOURCE_SLUG_CHANGE)
            ->delete();

        // Upsert por (site, from_path): un mismo origen no acumula filas al re-moverse.
        Redirect::updateOrCreate(
            ['site_id' => $event->siteId, 'from_path' => $event->oldPath],
            [
                'to_path' => $event->newPath,
                'status' => 301,
                'source' => Redirect::SOURCE_SLUG_CHANGE,
                'is_active' => true,
            ],
        );
    }
}
