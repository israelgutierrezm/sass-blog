<?php

declare(strict_types=1);

namespace App\Modules\Newsletter\Application\Jobs;

use App\Modules\Newsletter\Infrastructure\Models\Campaign;
use App\Modules\Newsletter\Infrastructure\Models\CampaignSend;
use App\Modules\Newsletter\Infrastructure\Models\Subscriber;
use App\Modules\Newsletter\Mail\CampaignMail;
use App\Modules\Shared\Domain\Tenancy\WorkspaceContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envía una campaña a los suscriptores CONFIRMADOS del sitio (ADR-022). Corre en la cola,
 * dentro del WorkspaceContext del tenant, por lotes (`send_batch`).
 *
 * Como mucho UN correo por destinatario, aun con varios workers o reintentos: antes de enviar se
 * RECLAMA el destinatario insertando su fila `campaign_sends` (`pending`); la unique
 * (campaign_id, subscriber_id) hace que sólo un worker gane el reclamo. Los contadores se
 * recalculan desde esas filas. Si el job muere, `failed()` deja la campaña en `failed` (no
 * colgada en `sending`); reenviarla REANUDA sin repetir a quien ya recibió.
 */
final class SendCampaign implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Segundos antes de dar el job por colgado (y llamar a failed()). */
    public int $timeout = 900;

    /** Sin reintento automático: se reanuda a mano (reenviar) y es idempotente. */
    public int $tries = 1;

    public function __construct(
        public readonly int $campaignId,
        public readonly int $workspaceId,
    ) {}

    public function handle(WorkspaceContext $context): void
    {
        $context->runFor($this->workspaceId, function (): void {
            $campaign = Campaign::find($this->campaignId);
            if ($campaign === null) {
                return;
            }

            $confirmed = fn () => Subscriber::forSite($campaign->site_id)->where('status', Subscriber::STATUS_CONFIRMED);

            $campaign->update([
                'status' => Campaign::STATUS_SENDING,
                'recipients_count' => $confirmed()->count(),
            ]);

            $confirmed()->chunkById((int) config('sassblog.newsletter.send_batch'), function ($subscribers) use ($campaign): void {
                foreach ($subscribers as $subscriber) {
                    $this->deliver($campaign, $subscriber);
                }
            });

            $campaign->update([
                'status' => Campaign::STATUS_SENT,
                'sent_at' => now(),
                ...$this->counts(),
            ]);
        });
    }

    /**
     * El job murió (excepción o timeout): la campaña queda `failed` con los contadores parciales
     * (cuánto llegó a enviarse) y se puede reanudar.
     */
    public function failed(?Throwable $exception): void
    {
        app(WorkspaceContext::class)->runFor($this->workspaceId, function (): void {
            Campaign::query()
                ->whereKey($this->campaignId)
                ->where('status', Campaign::STATUS_SENDING)
                ->update(['status' => Campaign::STATUS_FAILED, ...$this->counts()]);
        });
    }

    /**
     * Contadores recalculados desde `campaign_sends` (la fuente de verdad del envío).
     *
     * @return array{sent_count: int, failed_count: int}
     */
    private function counts(): array
    {
        $count = fn (string $status): int => CampaignSend::where('campaign_id', $this->campaignId)->where('status', $status)->count();

        return [
            'sent_count' => $count(CampaignSend::STATUS_SENT),
            'failed_count' => $count(CampaignSend::STATUS_FAILED),
        ];
    }

    private function deliver(Campaign $campaign, Subscriber $subscriber): void
    {
        // Atajo barato al reanudar: ya procesado → saltar sin provocar la excepción de la unique.
        if (CampaignSend::where('campaign_id', $campaign->id)->where('subscriber_id', $subscriber->id)->exists()) {
            return;
        }

        try {
            $send = CampaignSend::create([
                'campaign_id' => $campaign->id,
                'subscriber_id' => $subscriber->id,
                'status' => CampaignSend::STATUS_PENDING,
            ]);
        } catch (UniqueConstraintViolationException) {
            return; // otro worker lo reclamó entre el chequeo y el insert
        }

        $unsubscribeUrl = route('api.v1.public.newsletter.unsubscribe', ['token' => $subscriber->unsubscribe_token]);

        try {
            Mail::to($subscriber->email)->send(new CampaignMail($campaign->subject, $campaign->body, $unsubscribeUrl));
            $send->update(['status' => CampaignSend::STATUS_SENT, 'sent_at' => now()]);
        } catch (Throwable) {
            $send->update(['status' => CampaignSend::STATUS_FAILED]);
        }
    }
}
