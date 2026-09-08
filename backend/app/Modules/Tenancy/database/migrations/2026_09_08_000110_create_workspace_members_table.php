<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Membresías: qué usuario pertenece a qué workspace, con qué rol.
 *
 * `role` es el rol de workspace (owner/admin/editor/viewer). Es la fuente para
 * asignar el rol de Spatie dentro del team=workspace; las verificaciones de
 * permiso pasan por Spatie/Policies, no por esta columna.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role')->default('viewer');
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            // Una persona no puede pertenecer dos veces al mismo workspace.
            $table->unique(['workspace_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_members');
    }
};
