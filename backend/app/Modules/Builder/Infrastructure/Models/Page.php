<?php

declare(strict_types=1);

namespace App\Modules\Builder\Infrastructure\Models;

use App\Modules\Shared\Domain\Support\Concerns\HasPublicUlid;
use App\Modules\Shared\Domain\Tenancy\Concerns\BelongsToWorkspace;
use App\Modules\Sites\Infrastructure\Models\Site;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Página editorial dentro de un Site. Scopeada por workspace_id (y site_id).
 *
 * No guarda contenido: apunta a page_versions (draft_version_id/published_version_id).
 * Los punteros NO son fillable: los mueve la lógica de publicación (PublishPage).
 *
 * @property int $id
 * @property string $ulid
 * @property int $workspace_id
 * @property int $site_id
 * @property string $title
 * @property string $path
 * @property string $status
 * @property int|null $draft_version_id
 * @property int|null $published_version_id
 */
final class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use BelongsToWorkspace;

    use HasFactory;
    use HasPublicUlid;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    // Tipo de página (ADR-011): estándar (por path) vs plantilla de detalle de
    // colección (path NULL, rellenada con datos de una Entry vía bindings).
    public const KIND_STANDARD = 'standard';

    public const KIND_COLLECTION_TEMPLATE = 'collection_template';

    protected $fillable = [
        'site_id',
        'title',
        'path',
        'status',
        'created_by',
    ];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    protected static function newFactory(): PageFactory
    {
        return PageFactory::new();
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * @return BelongsTo<PageVersion, $this>
     */
    public function draftVersion(): BelongsTo
    {
        return $this->belongsTo(PageVersion::class, 'draft_version_id');
    }

    /**
     * @return BelongsTo<PageVersion, $this>
     */
    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(PageVersion::class, 'published_version_id');
    }

    /**
     * @return HasMany<PageVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(PageVersion::class);
    }
}
