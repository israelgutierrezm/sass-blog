<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Domains\Infrastructure\Models\SiteDomain;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SiteDomain>
 *
 * Requiere site_id (o ->for($site)).
 */
class SiteDomainFactory extends Factory
{
    protected $model = SiteDomain::class;

    public function definition(): array
    {
        return [
            'hostname' => Str::lower(fake()->unique()->domainName()),
            'status' => SiteDomain::STATUS_PENDING,
            'ssl_status' => SiteDomain::SSL_NONE,
            'verification_token' => Str::random(32),
            'is_primary' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => SiteDomain::STATUS_ACTIVE,
            'verified_at' => now(),
        ]);
    }
}
