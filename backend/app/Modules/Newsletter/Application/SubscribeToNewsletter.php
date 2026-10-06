<?php

declare(strict_types=1);

namespace App\Modules\Newsletter\Application;

use App\Modules\Newsletter\Infrastructure\Models\Subscriber;
use App\Modules\Newsletter\Mail\ConfirmationMail;
use App\Modules\Shared\Domain\Tenancy\WorkspaceContext;
use App\Modules\Sites\Infrastructure\Models\Site;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Alta de un suscriptor con DOBLE opt-in (ADR-022). Corre en el WorkspaceContext del sitio.
 * Crea (o reactiva) un `pending` con tokens frescos y encola el correo de confirmación. Si el
 * email ya está `confirmed`, no hace nada (ni reenvía).
 *
 * Anti-abuso: el endpoint es público y, con CORS abierto, cualquier web puede dispararlo desde
 * el navegador de sus visitas. Sin tope serviría para bombardear de correos a una víctima o
 * como relay de spam (el asunto lleva el nombre del sitio, que elige el tenant). Por eso hay un
 * tope por DESTINATARIO sumando todos los sitios y otro por SITIO; al superarlos simplemente no
 * se envía (la respuesta es la misma: no filtra nada).
 */
final class SubscribeToNewsletter
{
    /** Correos de confirmación por dirección y hora, sumando TODOS los sitios de la plataforma. */
    public const PER_RECIPIENT_PER_HOUR = 3;

    /** Correos de confirmación por sitio y hora (acota a un tenant que abuse del formulario). */
    public const PER_SITE_PER_HOUR = 200;

    public function __construct(private readonly WorkspaceContext $context) {}

    public function handle(Site $site, string $email): void
    {
        $this->context->runFor($site->workspace_id, function () use ($site, $email): void {
            $subscriber = Subscriber::forSite($site->id)->where('email', $email)->first();

            if ($subscriber !== null && $subscriber->isConfirmed()) {
                return;
            }

            if (! $this->allowConfirmationMail($site, $email)) {
                return;
            }

            if ($subscriber === null) {
                $subscriber = new Subscriber;
                $subscriber->site_id = $site->id;
                $subscriber->email = $email;
                $subscriber->unsubscribe_token = Str::random(64);
            }

            $subscriber->status = Subscriber::STATUS_PENDING;
            $subscriber->confirmation_token = Str::random(64);
            $subscriber->confirmed_at = null;
            $subscriber->unsubscribed_at = null;
            $subscriber->save();

            $confirmUrl = route('api.v1.public.newsletter.confirm', ['token' => $subscriber->confirmation_token]);

            // En cola: la respuesta no espera al SMTP (y el tiempo de respuesta deja de delatar
            // si la dirección ya estaba suscrita).
            Mail::to($subscriber->email)->queue(new ConfirmationMail($site->name, $confirmUrl));
        });
    }

    /**
     * Cuenta y aplica los topes. Las claves viven en la caché (no en tablas de tenant): el tope
     * por destinatario es de PLATAFORMA a propósito, y usa un hash para no guardar el email.
     */
    private function allowConfirmationMail(Site $site, string $email): bool
    {
        $recipientKey = 'newsletter-confirm:recipient:'.sha1(Str::lower($email));
        $siteKey = 'newsletter-confirm:site:'.$site->id;

        if (RateLimiter::tooManyAttempts($recipientKey, self::PER_RECIPIENT_PER_HOUR)
            || RateLimiter::tooManyAttempts($siteKey, self::PER_SITE_PER_HOUR)) {
            return false;
        }

        RateLimiter::hit($recipientKey, 3600);
        RateLimiter::hit($siteKey, 3600);

        return true;
    }
}
