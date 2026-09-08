<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Autores: identidad editorial por-sitio (byline). Puede o no mapear a un usuario
 * (user_id nullable → autor invitado). Scopeado por workspace_id + site_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authors', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('bio')->nullable();
            $table->string('avatar_url')->nullable(); // MVP: URL; media_id en Fase 4
            $table->string('email')->nullable();
            $table->json('links')->nullable(); // redes; presentacional
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['workspace_id', 'site_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authors');
    }
};
