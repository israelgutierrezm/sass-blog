<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Models;

use App\Modules\Builder\Infrastructure\Models\Page;
use App\Modules\Content\Domain\CollectionKind;
use App\Modules\Shared\Domain\Support\Concerns\HasPublicUlid;
use App\Modules\Shared\Domain\Tenancy\Concerns\BelongsToWorkspace;
use App\Modules\Shared\Domain\Tenancy\Concerns\ScopedToSite;
use App\Modules\Sites\Infrastructure\Models\Site;
use Database\Factories\CollectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tipo de contenido definido por-sitio (ADR-009). Scopeado por workspace + site.
 *
 * @property int $id
 * @property string $ulid
 * @property int $workspace_id
 * @property int $site_id
 * @property string $handle
 * @property CollectionKind $kind
 * @property string|null $route_prefix
 * @property int|null $template_page_id
 */
final class Collection extends Model
{
    /** @use HasFactory<CollectionFactory> */
    use BelongsToWorkspace;

    use HasFactory;
    use HasPublicUlid;
    use ScopedToSite;
    use SoftDeletes;

    protected $fillable = [
        'site_id',
        'handle',
        'name',
        'name_singular',
        'description',
        'kind',
        'route_prefix',
        'template_page_id',
        'created_by',
    ];

    protected $attributes = [
        'kind' => 'generic',
    ];

    protected function casts(): array
    {
        return ['kind' => CollectionKind::class];
    }

    protected static function newFactory(): CollectionFactory
    {
        return CollectionFactory::new();
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** @return HasMany<CollectionField, $this> */
    public function fields(): HasMany
    {
        return $this->hasMany(CollectionField::class)->orderBy('position');
    }

    /** @return HasMany<Entry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    /** @return HasMany<Category, $this> */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /** @return BelongsTo<Page, $this> */
    public function templatePage(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'template_page_id');
    }
}
