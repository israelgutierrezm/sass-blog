<?php

declare(strict_types=1);

namespace App\Modules\Navigation\Infrastructure\Models;

use App\Modules\Shared\Domain\Support\Concerns\HasPublicUlid;
use App\Modules\Shared\Domain\Tenancy\Concerns\BelongsToWorkspace;
use App\Modules\Shared\Domain\Tenancy\Concerns\ScopedToSite;
use Database\Factories\MenuItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ítem de menú jerárquico (ADR-017). Guarda una REFERENCIA (`link_type` +
 * `target_ulid`/`url`), no el path: se resuelve en render (respeta slug history).
 * Jerarquía por adjacency list (`parent_id` + `position`).
 *
 * @property int $id
 * @property string $ulid
 * @property int $site_id
 * @property int $menu_id
 * @property int|null $parent_id
 * @property string $label
 * @property string $link_type
 * @property string|null $target_ulid
 * @property string|null $url
 * @property int $position
 */
final class MenuItem extends Model
{
    /** @use HasFactory<MenuItemFactory> */
    use BelongsToWorkspace;

    use HasFactory;
    use HasPublicUlid;
    use ScopedToSite;

    public const LINK_PAGE = 'page';

    public const LINK_ENTRY = 'entry';

    public const LINK_COLLECTION = 'collection';

    public const LINK_URL = 'url';

    public const LINK_HOME = 'home';

    /** @var list<string> */
    public const LINK_TYPES = [
        self::LINK_PAGE,
        self::LINK_ENTRY,
        self::LINK_COLLECTION,
        self::LINK_URL,
        self::LINK_HOME,
    ];

    protected $fillable = [
        'site_id',
        'menu_id',
        'parent_id',
        'label',
        'link_type',
        'target_ulid',
        'url',
        'position',
    ];

    protected $attributes = [
        'position' => 0,
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Menu, $this>
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /**
     * @return BelongsTo<MenuItem, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('position');
    }

    protected static function newFactory(): MenuItemFactory
    {
        return MenuItemFactory::new();
    }
}
