<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workspaces: la raíz de tenencia (ADR-001). Un usuario pertenece a varios
 * workspaces; un workspace contiene varios sites.
 *
 * Esta tabla NO lleva workspace_id: es el tenant, no está dentro de otro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            // Dueño del workspace. RESTRICT: un usuario con workspaces no se borra sin más.
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            // Workspace personal creado al registrarse (no eliminable por el usuario).
            $table->boolean('personal')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('owner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspaces');
    }
};
