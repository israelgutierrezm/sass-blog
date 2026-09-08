<?php

declare(strict_types=1);

namespace App\Modules\Sites\Infrastructure\Models;

use App\Modules\Shared\Domain\Support\Concerns\HasPublicUlid;
use App\Modules\Shared\Domain\Tenancy\Concerns\BelongsToWorkspace;
use App\Modules\Tenancy\Infrastructure\Models\Workspace;
use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Site: un sitio web dentro de un workspace. Scopeado por workspace_id.
 *
 * @property int $id
 * @property string $ulid
 * @property int $workspace_id
 * @property string $name
 * @property string $slug
 * @property string $status
 * @property string|null $primary_domain
 * @property array<string, mixed>|null $settings
 */
final class Site extends Model
{
    /** @use HasFactory<SiteFactory> */
    use BelongsToWorkspace;

    use HasFactory;
    use HasPublicUlid;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'name',
        'slug',
        'status',
        'primary_domain',
        'settings',
    ];

    /**
     * Default en el modelo, no sólo en la migración: así la respuesta de creación
     * refleja 'draft' en vez de null antes de releer de la base.
     */
    protected $attributes = [
        'status' => self::STATUS_DRAFT,
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    /**
     * El modelo vive en un módulo; el resolvedor por defecto buscaría el factory
     * bajo Database\Factories\Modules\... Se declara explícitamente.
     */
    protected static function newFactory(): SiteFactory
    {
        return SiteFactory::new();
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
