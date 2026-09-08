<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Models\User;
use App\Modules\Tenancy\Application\CreateWorkspace;
use App\Modules\Tenancy\Infrastructure\Models\Workspace;
use Illuminate\Support\Facades\DB;

/**
 * Alta de una persona en el SaaS: crea el usuario y su workspace personal.
 *
 * El workspace personal dispara la provisión de RBAC y la suscripción free por
 * evento (ver CreateWorkspace y sus listeners).
 */
final class RegisterUser
{
    public function __construct(private readonly CreateWorkspace $createWorkspace) {}

    /**
     * @return array{user: User, workspace: Workspace}
     */
    public function handle(string $name, string $email, string $password): array
    {
        return DB::transaction(function () use ($name, $email, $password): array {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => $password, // el cast 'hashed' del modelo lo cifra
            ]);

            $workspace = $this->createWorkspace->handle($user, "Espacio de {$name}", personal: true);

            return ['user' => $user, 'workspace' => $workspace];
        });
    }
}
