<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Models;

use App\Models\User;
use App\Modules\Shared\Domain\Support\Concerns\HasPublicUlid;
use App\Modules\Shared\Domain\Tenancy\Concerns\BelongsToWorkspace;
use App\Modules\Shared\Domain\Tenancy\Concerns\ScopedToSite;
use Database\Factories\AuthorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Identidad editorial por-sitio (byline). user_id nullable = autor invitado.
 *
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string $slug
 */
final class Author extends Model
{
    /** @use HasFactory<AuthorFactory> */
    use BelongsToWorkspace;

    use HasFactory;
    use HasPublicUlid;
    use ScopedToSite;
    use SoftDeletes;

    protected $fillable = [
        'site_id',
        'user_id',
        'name',
        'slug',
        'bio',
        'avatar_url',
        'email',
        'links',
        'position',
        'created_by',
    ];

    protected function casts(): array
    {
        return ['links' => 'array'];
    }

    protected static function newFactory(): AuthorFactory
    {
        return AuthorFactory::new();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Entry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }
}
