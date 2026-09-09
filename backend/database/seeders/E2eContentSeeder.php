<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Billing\Infrastructure\Models\Plan;
use App\Modules\Identity\Application\RegisterUser;
use App\Modules\Shared\Domain\Tenancy\WorkspaceContext;
use Illuminate\Database\Seeder;

/**
 * Usuario Pro para el E2E de contenido (FASE 3): el CMS exige la capability
 * cms.collections, que sólo trae el plan Pro. El sitio lo crea el propio E2E por la
 * UI (para ejercitar el sembrado del preset). Sólo se corre desde la config de
 * Playwright, nunca en el seed por defecto. Idempotente.
 */
final class E2eContentSeeder extends Seeder
{
    public function run(): void
    {
        $email = 'cms-e2e@sass.local';

        if (User::where('email', $email)->exists()) {
            return;
        }

        $registration = app(RegisterUser::class)->handle('CMS E2E', $email, 'Password!123');
        $workspace = $registration['workspace'];
        $pro = Plan::where('key', 'pro')->firstOrFail();

        app(WorkspaceContext::class)->runFor(
            $workspace->id,
            fn () => $workspace->subscription()->update(['plan_id' => $pro->id]),
        );
    }
}
