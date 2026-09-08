<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PageVersions: instantáneas del page schema (ADR-002). El draft es mutable; la
 * versión publicada es INMUTABLE (guarda de modelo). Append-only, sin softDeletes.
 *
 * `schema` es JSON (fuente de verdad del contenido de la página). 'schema' es
 * palabra reservada de MySQL pero Laravel siempre la entrecomilla con backticks
 * (decisión D5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_versions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignId('page_id')->constrained('pages')->cascadeOnDelete();
            $table->unsignedInteger('version_number'); // monotónico por página (1,2,3…)
            $table->string('status', 20)->default('draft'); // draft | published
            $table->unsignedSmallInteger('schema_version')->default(1); // versión de FORMATO
            $table->json('schema'); // el page schema (ADR-002)
            $table->string('label')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Numeración monotónica por página, sin huecos/duplicados.
            $table->unique(['workspace_id', 'page_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_versions');
    }
};
