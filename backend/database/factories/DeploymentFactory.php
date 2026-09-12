<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Publishing\Infrastructure\Models\Deployment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deployment>
 *
 * Requiere site_id (o ->for($site)).
 */
class DeploymentFactory extends Factory
{
    protected $model = Deployment::class;

    public function definition(): array
    {
        return [
            'target' => Deployment::TARGET_STATIC,
            'status' => Deployment::STATUS_PENDING,
            'published_hash' => hash('sha256', (string) fake()->unique()->numberBetween(1, 1_000_000)),
        ];
    }

    public function success(): static
    {
        return $this->state(fn () => [
            'status' => Deployment::STATUS_SUCCESS,
            'artifact_ref' => 'deployments/'.fake()->uuid().'.zip',
            'bytes' => fake()->numberBetween(1000, 500000),
        ]);
    }
}
