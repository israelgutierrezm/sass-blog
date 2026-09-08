<?php

declare(strict_types=1);

namespace App\Modules\Billing\Infrastructure\Models;

use App\Modules\Shared\Domain\Support\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Plan: catálogo global (no scopeado). El cobro real es futuro; aquí sólo el
 * modelo y qué capabilities otorga.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property bool $active
 */
final class Plan extends Model
{
    use HasPublicUlid;

    protected $fillable = [
        'key',
        'name',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    /**
     * Capabilities otorgadas por el plan, con su límite (pivote plan_capabilities).
     *
     * @return BelongsToMany<Capability, $this>
     */
    public function capabilities(): BelongsToMany
    {
        return $this->belongsToMany(Capability::class, 'plan_capabilities')
            ->withPivot('limit')
            ->withTimestamps();
    }
}
