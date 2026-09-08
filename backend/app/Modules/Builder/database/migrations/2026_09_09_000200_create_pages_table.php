<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pages: la página como entidad editorial dentro de un Site. NO guarda el
 * contenido; apunta a versiones (page_versions). Scopeada por workspace_id Y
 * site_id (denormalización deliberada, como audit_logs).
 *
 * Los punteros draft_version_id/published_version_id se crean como columnas SIN FK
 * aquí; la FK real se añade en 2026_09_09_000220 para romper la dependencia
 * circular pages <-> page_versions (ADR / D8).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->string('title');
            $table->string('path'); // ruta normalizada con '/' inicial; '/' = home
            $table->string('status', 20)->default('draft'); // draft | published | archived
            $table->unsignedBigInteger('draft_version_id')->nullable();
            $table->unsignedBigInteger('published_version_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Una página por ruta por sitio; sirve el lookup del renderer.
            $table->unique(['workspace_id', 'site_id', 'path']);
            // Listado admin por estado + sitemap de publicadas.
            $table->index(['workspace_id', 'site_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
