<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Modules\Tenancy\Infrastructure\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Workspace>
 */
class WorkspaceFactory extends Factory
{
    protected $model = Workspace::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 999999),
            'owner_id' => User::factory(),
            'personal' => false,
        ];
    }

    public function personal(): static
    {
        return $this->state(fn () => ['personal' => true]);
    }
}
