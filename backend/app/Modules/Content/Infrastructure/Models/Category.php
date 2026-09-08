<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Models;

use App\Modules\Shared\Domain\Support\Concerns\HasPublicUlid;
use App\Modules\Shared\Domain\Tenancy\Concerns\BelongsToWorkspace;
use App\Modules\Shared\Domain\Tenancy\Concerns\ScopedToSite;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Taxonomía por colección (plana en MVP). Scopeada por workspace + site.
 *
 * @property int $id
 * @property string $ulid
 * @property int $collection_id
 * @property string $name
 * @property string $slug
 */
final class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use BelongsToWorkspace;

    use HasFactory;
    use HasPublicUlid;
    use ScopedToSite;

    protected $fillable = [
        'site_id',
        'collection_id',
        'name',
        'slug',
        'description',
        'position',
        'created_by',
    ];

    protected static function newFactory(): CategoryFactory
    {
        return CategoryFactory::new();
    }

    /** @return BelongsTo<Collection, $this> */
    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    /** @return BelongsToMany<Entry, $this> */
    public function entries(): BelongsToMany
    {
        return $this->belongsToMany(Entry::class, 'category_entry')
            ->withPivot('workspace_id', 'site_id')
            ->withTimestamps();
    }
}
