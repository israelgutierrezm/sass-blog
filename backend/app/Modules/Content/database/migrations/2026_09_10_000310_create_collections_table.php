<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Colecciones: tipos de contenido definidos por-sitio (ADR-009). `handle` es el
 * identificador máquina estable; `route_prefix` la base de la URL de detalle;
 * `template_page_id` la Page plantilla (kind=collection_template) que renderiza el
 * detalle con bindings (ADR-011).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->string('handle');
            $table->string('name');
            $table->string('name_singular')->nullable();
            $table->string('description')->nullable();
            $table->string('kind', 20)->default('generic'); // generic | article
            $table->string('route_prefix')->nullable(); // p.ej. 'blog'; NULL = sin detalle público
            $table->foreignId('template_page_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['workspace_id', 'site_id', 'handle']);
            // route_prefix único por sitio (múltiples NULL conviven en MySQL).
            $table->unique(['workspace_id', 'site_id', 'route_prefix']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collections');
    }
};
