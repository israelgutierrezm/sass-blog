<?php

declare(strict_types=1);

namespace App\Modules\Navigation\Infrastructure\Models;

use App\Modules\Shared\Domain\Support\Concerns\HasPublicUlid;
use App\Modules\Shared\Domain\Tenancy\Concerns\BelongsToWorkspace;
use App\Modules\Shared\Domain\Tenancy\Concerns\ScopedToSite;
use Database\Factories\MenuFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Menú de navegación por-sitio (ADR-017). Contenedor de `menu_items` jerárquicos.
 * `handle` libre y único por sitio; los enlaces se resuelven en render.
 *
 * @property int $id
 * @property string $ulid
 * @property int $site_id
 * @property string $handle
 * @property string $name
 */
final class Menu extends Model
{
    /** @use HasFactory<MenuFactory> */
    use BelongsToWorkspace;

    use HasFactory;
    use HasPublicUlid;
    use ScopedToSite;

    public const HANDLE_PRIMARY = 'primary';

    public const HANDLE_FOOTER = 'footer';

    protected $fillable = [
        'site_id',
        'handle',
        'name',
        'created_by',
    ];

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    protected static function newFactory(): MenuFactory
    {
        return MenuFactory::new();
    }
}
