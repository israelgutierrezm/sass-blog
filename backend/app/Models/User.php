<?php

declare(strict_types=1);

namespace App\Models;

use App\Modules\Shared\Domain\Support\Concerns\HasPublicUlid;
use App\Modules\Tenancy\Infrastructure\Models\Workspace;
use App\Modules\Tenancy\Infrastructure\Models\WorkspaceMember;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Usuario global del SaaS.
 *
 * Es la ÚNICA entidad de dominio que vive en App\Models (convención del framework:
 * lo referencian Sanctum, config/auth.php y el factory). Todo lo demás vive en
 * módulos. No lleva global scope de workspace: una persona pertenece a varios
 * workspaces; el aislamiento vive en `workspace_members`.
 *
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string $email
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens;

    use HasFactory;
    use HasPublicUlid;
    use HasRoles;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Workspaces que esta persona posee.
     *
     * @return HasMany<Workspace, $this>
     */
    public function ownedWorkspaces(): HasMany
    {
        return $this->hasMany(Workspace::class, 'owner_id');
    }

    /**
     * Membresías en TODOS sus workspaces.
     *
     * Legítimamente cross-workspace: sólo para el flujo de identidad (el selector de
     * workspace al iniciar sesión), que ocurre ANTES de que exista contexto. Por eso
     * omite el global scope de WorkspaceMember; usarla desde código de dominio
     * violaría el aislamiento de ADR-001.
     *
     * @return HasMany<WorkspaceMember, $this>
     */
    public function workspaceMemberships(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class)->withoutGlobalScopes();
    }

    /**
     * Workspaces a los que pertenece (vía pivote workspace_members).
     *
     * @return BelongsToMany<Workspace, $this>
     */
    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_members')
            ->withPivot('role')
            ->withTimestamps();
    }
}
