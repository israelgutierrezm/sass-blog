<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entries: núcleo transaccional del CMS (ADR-009). Columnas universales reales +
 * `data` JSON (campos personalizados, validado dinámicamente contra los
 * collection_fields). MUTABLE, sin versionado (D1): el flujo editorial vive en
 * `status`; la superficie pública sólo lee `published`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entries', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignId('collection_id')->constrained('collections')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug'); // resuelve {slug} del route_prefix
            $table->string('status', 20)->default('draft'); // draft | published | archived
            $table->timestamp('published_at')->nullable(); // NO fillable: lo mueve PublishEntry
            $table->foreignId('author_id')->nullable()->constrained('authors')->nullOnDelete();
            $table->json('data'); // campos personalizados (default {} vía el modelo)
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['workspace_id', 'site_id', 'collection_id', 'slug']);
            // Camino caliente del CollectionGrid/feed y del sitemap (filtro + orden).
            // Nombre explícito: el autogenerado excede los 64 chars de MySQL.
            $table->index(
                ['workspace_id', 'site_id', 'collection_id', 'status', 'published_at'],
                'entries_feed_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entries');
    }
};
