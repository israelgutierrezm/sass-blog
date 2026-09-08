<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Models;

use App\Modules\Shared\Domain\Support\Concerns\HasPublicUlid;
use App\Modules\Shared\Domain\Tenancy\Concerns\BelongsToWorkspace;
use App\Modules\Shared\Domain\Tenancy\Concerns\ScopedToSite;
use Database\Factories\EntryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Registro de contenido: columnas universales + `data` JSON (ADR-009). MUTABLE,
 * sin versionado (D1). Scopeado por workspace + site.
 *
 * @property int $id
 * @property string $ulid
 * @property int $collection_id
 * @property string $title
 * @property string $slug
 * @property string $status
 * @property int|null $author_id
 * @property array<string, mixed> $data
 */
final class Entry extends Model
{
    /** @use HasFactory<EntryFactory> */
    use BelongsToWorkspace;

    use HasFactory;
    use HasPublicUlid;
    use ScopedToSite;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'site_id',
        'collection_id',
        'title',
        'slug',
        'status',
        'author_id',
        'data',
        'created_by',
        'updated_by',
    ];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'data' => '{}',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'published_at' => 'datetime',
        ];
    }

    protected static function newFactory(): EntryFactory
    {
        return EntryFactory::new();
    }

    /** Sólo contenido público: publicado y con fecha de publicación llegada. */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where($this->qualifyColumn('status'), self::STATUS_PUBLISHED)
            ->where($this->qualifyColumn('published_at'), '<=', now());
    }

    /** @return BelongsTo<Collection, $this> */
    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    /** @return BelongsTo<Author, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    /** @return BelongsToMany<Category, $this> */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_entry')
            ->withPivot('workspace_id', 'site_id')
            ->withTimestamps();
    }

    /**
     * Sincroniza categorías rellenando el tenant del pivote (que no tiene scope).
     *
     * @param  array<int, int>  $categoryIds
     */
    public function syncCategories(array $categoryIds): void
    {
        $payload = [];
        foreach ($categoryIds as $id) {
            $payload[$id] = ['workspace_id' => $this->workspace_id, 'site_id' => $this->site_id];
        }
        $this->categories()->sync($payload);
    }
}
