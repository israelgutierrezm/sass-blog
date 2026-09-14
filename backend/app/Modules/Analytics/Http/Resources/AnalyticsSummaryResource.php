<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Salida del dashboard de analítica (ADR-021). Envuelve el array ya calculado por
 * MetricsQuery + el rango y el flag `advanced` (plan). `top_referrers` es null sin
 * `analytics.advanced` (Pro).
 *
 * @property array<string, mixed> $resource
 */
final class AnalyticsSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'range' => $this->resource['range'],
            'advanced' => $this->resource['advanced'],
            'totals' => $this->resource['totals'],
            'series' => $this->resource['series'],
            'top_pages' => $this->resource['top_pages'],
            'top_referrers' => $this->resource['top_referrers'],
        ];
    }
}
