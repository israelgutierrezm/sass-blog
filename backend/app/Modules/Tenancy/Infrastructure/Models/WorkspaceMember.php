<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Models;

use App\Models\User;
use App\Modules\Shared\Domain\Tenancy\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Membresía: qué usuario pertenece a qué workspace, con qué rol.
 *
 * Scopeada por workspace (BelongsToWorkspace). Para listar las membresías de una
 * persona a través de sus workspaces (login/selector) se usa
 * User::workspaceMemberships(), que omite el scope a propósito.
 *
 * @property int $id
 * @property int $workspace_id
 * @property int $user_id
 * @property string $role
 */
final class WorkspaceMember extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
        'user_id',
        'role',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
