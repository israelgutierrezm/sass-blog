<?php

declare(strict_types=1);

namespace App\Modules\Newsletter\Http\Resources;

use App\Modules\Newsletter\Infrastructure\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Campaign
 */
final class CampaignResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'subject' => $this->subject,
            'body' => $this->body,
            'status' => $this->status,
            'recipients_count' => $this->recipients_count,
            'sent_count' => $this->sent_count,
            'failed_count' => $this->failed_count,
            'sent_at' => $this->sent_at,
            'created_at' => $this->created_at,
        ];
    }
}
