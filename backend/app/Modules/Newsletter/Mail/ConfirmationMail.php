<?php

declare(strict_types=1);

namespace App\Modules\Newsletter\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Correo de confirmación del doble opt-in (ADR-022): lleva el link con el `confirmation_token`.
 * HTML inline (sin vista): plantilla mínima del MVP.
 */
final class ConfirmationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $siteName,
        public readonly string $confirmUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Confirma tu suscripción a {$this->siteName}");
    }

    public function content(): Content
    {
        $html = '<p>Gracias por suscribirte a <strong>'.e($this->siteName).'</strong>.</p>'
            .'<p>Confirma tu suscripción para empezar a recibir novedades:</p>'
            .'<p><a href="'.e($this->confirmUrl).'">Confirmar suscripción</a></p>'
            .'<p style="color:#888;font-size:12px">Si no fuiste tú, ignora este correo.</p>';

        return new Content(htmlString: $html);
    }
}
