<?php

declare(strict_types=1);

namespace App\Modules\Newsletter\Application;

use App\Modules\Newsletter\Infrastructure\Models\Subscriber;
use App\Modules\Newsletter\Mail\ConfirmationMail;
use App\Modules\Shared\Domain\Tenancy\WorkspaceContext;
use App\Modules\Sites\Infrastructure\Models\Site;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Alta de un suscriptor con DOBLE opt-in (ADR-022). Corre en el WorkspaceContext del sitio.
 * Crea (o reactiva) un `pending` con tokens frescos y envía el correo de confirmación. Si el
 * email ya está `confirmed`, no hace nada (ni reenvía) — evita duplicados y spam de confirmación.
 */
final class SubscribeToNewsletter
{
    public function __construct(private readonly WorkspaceContext $context) {}

    public function handle(Site $site, string $email): void
    {
        $this->context->runFor($site->workspace_id, function () use ($site, $email): void {
            $subscriber = Subscriber::forSite($site->id)->where('email', $email)->first();

            if ($subscriber !== null && $subscriber->isConfirmed()) {
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

            Mail::to($subscriber->email)->send(new ConfirmationMail($site->name, $confirmUrl));
        });
    }
}
