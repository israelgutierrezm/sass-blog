<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Models;

use App\Modules\Content\Domain\Fields\FieldType;
use App\Modules\Shared\Domain\Support\Concerns\HasPublicUlid;
use App\Modules\Shared\Domain\Tenancy\Concerns\BelongsToWorkspace;
use App\Modules\Shared\Domain\Tenancy\Concerns\ScopedToSite;
use Database\Factories\CollectionFieldFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Campo personalizado de una colección: define y valida una clave de entries.data.
 *
 * @property int $id
 * @property string $ulid
 * @property int $collection_id
 * @property string $key
 * @property FieldType $type
 * @property bool $required
 * @property array<string, mixed>|null $config
 * @property int|null $related_collection_id
 */
final class CollectionField extends Model
{
    /** @use HasFactory<CollectionFieldFactory> */
    use BelongsToWorkspace;

    use HasFactory;
    use HasPublicUlid;
    use ScopedToSite;

    protected $fillable = [
        'site_id',
        'collection_id',
        'key',
        'label',
        'type',
        'required',
        'config',
        'related_collection_id',
        'position',
    ];

    protected $attributes = [
        'required' => false,
    ];

    protected function casts(): array
    {
        return [
            'type' => FieldType::class,
            'required' => 'boolean',
            'config' => 'array',
        ];
    }

    protected static function newFactory(): CollectionFieldFactory
    {
        return CollectionFieldFactory::new();
    }

    /** @return BelongsTo<Collection, $this> */
    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    /** @return BelongsTo<Collection, $this> */
    public function relatedCollection(): BelongsTo
    {
        return $this->belongsTo(Collection::class, 'related_collection_id');
    }
}
