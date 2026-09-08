<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de auditoría: INMUTABLE (sólo INSERT; sin updated_at).
 *
 * Registra operaciones relevantes (publicar, borrar, invitar, cambiar rol/dominio).
 * workspace_id/site_id/actor_id son nullable porque hay acciones de plataforma
 * (super admin) y de sistema sin actor humano. `metadata` es JSON (before/after,
 * contexto): uno de los usos legítimos de JSON del proyecto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('workspace_id')->nullable()->constrained('workspaces')->nullOnDelete();
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('entity_type')->nullable();
            $table->string('entity_id')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip', 45)->nullable();
            // Inmutable: sólo created_at. No timestamps() (no updated_at).
            $table->timestamp('created_at')->useCurrent();

            $table->index(['workspace_id', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
