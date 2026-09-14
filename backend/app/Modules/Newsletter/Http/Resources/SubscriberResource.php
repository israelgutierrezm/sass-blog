<?php

declare(strict_types=1);

namespace App\Modules\Newsletter\Http\Resources;

use App\Modules\Newsletter\Infrastructure\Models\Subscriber;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Subscriber
 */
final class SubscriberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'email' => $this->email,
            'status' => $this->status,
            'confirmed_at' => $this->confirmed_at,
            'created_at' => $this->created_at,
        ];
    }
}
