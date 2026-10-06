<?php

declare(strict_types=1);

namespace App\Modules\Newsletter\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Conteo de suscriptores por estado de un sitio (ADR-022). Lo usa el panel para mostrar a
 * cuántos llegaría una campaña sin paginar la lista completa.
 *
 * @property array{pending: int, confirmed: int, unsubscribed: int} $resource
 */
final class SubscriberStatsResource extends JsonResource
{
    /**
     * @return array<string, int>
     */
    public function toArray(Request $request): array
    {
        return [
            'pending' => $this->resource['pending'],
            'confirmed' => $this->resource['confirmed'],
            'unsubscribed' => $this->resource['unsubscribed'],
            'total' => $this->resource['pending'] + $this->resource['confirmed'] + $this->resource['unsubscribed'],
        ];
    }
}
