<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Tenancy\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Acota un modelo de dominio al site resuelto de la ruta (ADR-005 / ADR-015).
 *
 * El WorkspaceScope global ya impide fugas cross-workspace; este scope LOCAL
 * (explícito, no global) impide fugas cross-site DENTRO del workspace mientras se
 * difiere un SiteScope global. Todo modelo scopeado por site del CMS lo usa, y hay
 * tests de aislamiento por-site que lo vigilan.
 *
 * @method static Builder forSite(int $siteId)
 */
trait ScopedToSite
{
    public function scopeForSite(Builder $query, int $siteId): Builder
    {
        return $query->where($this->qualifyColumn('site_id'), $siteId);
    }
}
