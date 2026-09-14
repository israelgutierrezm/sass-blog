<?php

declare(strict_types=1);

namespace App\Modules\Newsletter\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Correo de una campaña (ADR-022). El cuerpo es HTML del owner (se inserta tal cual); se añade
 * un pie con el link de baja del suscriptor (obligatorio en cada envío).
 */
final class CampaignMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $campaignSubject,
        public readonly string $body,
        public readonly string $unsubscribeUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->campaignSubject);
    }

    public function content(): Content
    {
        $html = $this->body
            .'<hr><p style="color:#888;font-size:12px">Recibes este correo porque te suscribiste. '
            .'<a href="'.e($this->unsubscribeUrl).'">Darse de baja</a>.</p>';

        return new Content(htmlString: $html);
    }
}
