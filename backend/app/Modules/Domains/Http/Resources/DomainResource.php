<?php

declare(strict_types=1);

namespace App\Modules\Domains\Http\Resources;

use App\Modules\Domains\Infrastructure\Models\SiteDomain;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SiteDomain
 */
final class DomainResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $ip = (string) config('sassblog.domains.ingress_ip');

        return [
            'id' => $this->ulid,
            'hostname' => $this->hostname,
            'status' => $this->status,
            // Por qué falló la última verificación: ownership | routing | taken (ADR-025).
            'failure_reason' => $this->failure_reason,
            'ssl_status' => $this->ssl_status,
            'is_primary' => $this->is_primary,
            'verified_at' => $this->verified_at,
            // Registros DNS que el tenant debe publicar: TXT de propiedad + apuntado al ingress.
            'verification' => [
                'txt_name' => $this->challengeName(),
                'txt_value' => $this->challengeValue(),
                'cname' => (string) config('sassblog.domains.ingress_cname'),
                'ip' => $ip !== '' ? $ip : null,
            ],
            'created_at' => $this->created_at,
        ];
    }
}
