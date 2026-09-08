<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Semillas de plataforma (catálogos): permisos, capabilities y planes.
 *
 * NO crea usuarios/workspaces de demo aquí: eso vive en un seeder de demo aparte
 * cuando haga falta. El orden importa: los planes referencian capabilities.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RbacSeeder::class,
            CapabilitySeeder::class,
            PlanSeeder::class,
        ]);
    }
}
