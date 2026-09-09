<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Billing\Infrastructure\Models\Capability as CapabilityModel;
use App\Modules\Billing\Infrastructure\Models\Plan;
use App\Modules\Shared\Domain\Capabilities\Capability;
use Illuminate\Database\Seeder;

/**
 * Planes iniciales y las capabilities que otorga cada uno. Depende de que
 * CapabilitySeeder haya corrido antes (lo garantiza DatabaseSeeder).
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $free = Plan::updateOrCreate(['key' => 'free'], ['name' => 'Free', 'active' => true]);
        $pro = Plan::updateOrCreate(['key' => 'pro'], ['name' => 'Pro', 'active' => true]);
        $agency = Plan::updateOrCreate(['key' => 'agency'], ['name' => 'Agency', 'active' => true]);

        // Free: sin capabilities de pago.
        $this->grant($free, []);

        // Pro: creación de sitios avanzada.
        $this->grant($pro, [
            Capability::CmsCollections,
            Capability::MediaLibrary,
            Capability::SiteCustomDomain,
            Capability::SiteMultilanguage,
            Capability::BuilderCustomCode,
            Capability::SiteExportStatic,
            Capability::AnalyticsAdvanced,
        ]);

        // Agency: todo, incluida marca blanca.
        $this->grant($agency, Capability::cases());
    }

    /**
     * @param  list<Capability>  $capabilities
     */
    private function grant(Plan $plan, array $capabilities): void
    {
        $ids = [];

        foreach ($capabilities as $capability) {
            $model = CapabilityModel::where('key', $capability->value)->first();

            if ($model !== null) {
                $ids[$model->id] = ['limit' => null];
            }
        }

        $plan->capabilities()->sync($ids);
    }
}
