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
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envía una campaña a los suscriptores CONFIRMADOS del sitio (ADR-022). Corre en la cola,
 * dentro del WorkspaceContext del tenant. Por lotes (`send_batch`) para no cargar toda la lista
 * en memoria. IDEMPOTENTE: la unique de `campaign_sends` + el chequeo previo hacen que
 * re-ejecutar el job salte a quien ya recibió (no duplica correos). Los contadores se recalculan
 * desde `campaign_sends`, así que son correctos aun tras un reintento.
 */
final class SendCampaign implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

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
                'sent_count' => CampaignSend::where('campaign_id', $campaign->id)->where('status', CampaignSend::STATUS_SENT)->count(),
                'failed_count' => CampaignSend::where('campaign_id', $campaign->id)->where('status', CampaignSend::STATUS_FAILED)->count(),
            ]);
        });
    }

    private function deliver(Campaign $campaign, Subscriber $subscriber): void
    {
        // Idempotencia: si este destinatario ya se procesó, saltar (no reenviar).
        if (CampaignSend::where('campaign_id', $campaign->id)->where('subscriber_id', $subscriber->id)->exists()) {
            return;
        }

        $unsubscribeUrl = route('api.v1.public.newsletter.unsubscribe', ['token' => $subscriber->unsubscribe_token]);

        try {
            Mail::to($subscriber->email)->send(new CampaignMail($campaign->subject, $campaign->body, $unsubscribeUrl));
            $status = CampaignSend::STATUS_SENT;
        } catch (Throwable) {
            $status = CampaignSend::STATUS_FAILED;
        }

        CampaignSend::create([
            'campaign_id' => $campaign->id,
            'subscriber_id' => $subscriber->id,
            'status' => $status,
            'sent_at' => now(),
        ]);
    }
}
