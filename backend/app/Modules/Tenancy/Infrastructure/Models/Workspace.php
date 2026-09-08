<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Models;

use App\Models\User;
use App\Modules\Billing\Infrastructure\Models\Subscription;
use App\Modules\Shared\Domain\Support\Concerns\HasPublicUlid;
use App\Modules\Sites\Infrastructure\Models\Site;
use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Workspace: la raíz de tenencia (ADR-001).
 *
 * NO lleva BelongsToWorkspace: es el tenant mismo, no está dentro de otro. Su `id`
 * ES el workspace_id que scopea al resto del dominio.
 *
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string $slug
 * @property int $owner_id
 * @property bool $personal
 */
final class Workspace extends Model
{
    /** @use HasFactory<WorkspaceFactory> */
    use HasFactory;

    use HasPublicUlid;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'owner_id',
        'personal',
    ];

    protected function casts(): array
    {
        return [
            'personal' => 'boolean',
        ];
    }

    /**
     * El modelo vive en un módulo; el resolvedor por defecto buscaría el factory
     * bajo Database\Factories\Modules\... Se declara explícitamente.
     */
    protected static function newFactory(): WorkspaceFactory
    {
        return WorkspaceFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<WorkspaceMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    /**
     * @return HasMany<Site, $this>
     */
    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    /**
     * @return HasOne<Subscription, $this>
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }
}
