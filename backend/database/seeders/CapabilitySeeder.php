<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Billing\Infrastructure\Models\Capability as CapabilityModel;
use App\Modules\Shared\Domain\Capabilities\Capability;
use Illuminate\Database\Seeder;

/**
 * Refleja el enum Capability (fuente de verdad del catálogo cerrado) en la tabla
 * `capabilities`, para dar integridad referencial a plan_capabilities.
 */
class CapabilitySeeder extends Seeder
{
    public function run(): void
    {
        foreach (Capability::cases() as $capability) {
            CapabilityModel::updateOrCreate(
                ['key' => $capability->value],
                ['name' => $capability->label()],
            );
        }
    }
}
