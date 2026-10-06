<?php

declare(strict_types=1);

namespace App\Modules\Newsletter\Providers;

use App\Modules\Newsletter\Infrastructure\Models\Campaign;
use App\Modules\Newsletter\Infrastructure\Models\Subscriber;
use App\Modules\Newsletter\Policies\NewsletterPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Registro del módulo Newsletter (ADR-022): policies de suscriptores/campañas y los limiters
 * con nombre de la superficie pública (no comparten contador con otros endpoints de la IP).
 */
final class NewsletterServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Subscriber::class, NewsletterPolicy::class);
        Gate::policy(Campaign::class, NewsletterPolicy::class);

        // Alta desde el formulario del sitio: por IP del visitante. (El tope anti-bombardeo por
        // destinatario y por sitio vive en SubscribeToNewsletter.)
        RateLimiter::for('newsletter-subscribe', fn (Request $request) => Limit::perMinute(30)->by((string) $request->ip()));

        // Confirmar / darse de baja: por TOKEN (ver Http/Routes/public.php).
        RateLimiter::for('newsletter-token', fn (Request $request) => Limit::perMinute(30)->by((string) $request->query('token', '')));
    }
}
