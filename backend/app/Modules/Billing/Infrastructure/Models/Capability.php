<?php

declare(strict_types=1);

namespace App\Modules\Billing\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Capability: fila del catálogo cerrado de funcionalidades de plan (global).
 *
 * La fuente de verdad del catálogo es el enum
 * App\Modules\Shared\Domain\Capabilities\Capability; esta tabla lo refleja para
 * dar integridad referencial a plan_capabilities. El identificador público es `key`.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string|null $description
 */
final class Capability extends Model
{
    protected $fillable = [
        'key',
        'name',
        'description',
    ];

    public function getRouteKeyName(): string
    {
        return 'key';
    }

    /**
     * @return BelongsToMany<Plan, $this>
     */
    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'plan_capabilities')
            ->withPivot('limit')
            ->withTimestamps();
    }
}
