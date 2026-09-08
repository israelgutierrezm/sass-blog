<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sites: cada workspace contiene varios sitios. Scopeado por workspace_id.
 *
 * `settings` es JSON de configuración del sitio (branding, idiomas...): estructura
 * de contenido, no dato relacional (ADR-002). Los datos claramente relacionales
 * (dominios, páginas) tendrán sus propias tablas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('status')->default('draft'); // draft | published | archived
            $table->string('primary_domain')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // El slug es único DENTRO del workspace, no globalmente.
            $table->unique(['workspace_id', 'slug']);
            // Filtrado típico: sitios de un workspace por estado.
            $table->index(['workspace_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
