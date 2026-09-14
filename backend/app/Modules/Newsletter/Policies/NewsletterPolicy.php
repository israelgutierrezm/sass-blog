<?php

declare(strict_types=1);

namespace App\Modules\Newsletter\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Autorización de newsletter (RBAC, ADR-022). `newsletter.manage` (owner/admin/editor) cubre
 * gestionar suscriptores y campañas. El gating por PLAN del ENVÍO (`newsletter.send`, Pro) lo
 * hace la ruta con la capability, no la Policy. Registrada para Subscriber y Campaign.
 */
final class NewsletterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('newsletter.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('newsletter.manage');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->hasPermissionTo('newsletter.manage');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->hasPermissionTo('newsletter.manage');
    }
}
