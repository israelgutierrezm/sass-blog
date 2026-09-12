<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deployments: bitácora INMUTABLE de builds de un sitio (ADR-019, publishing.md). Sólo
 * avanza el `status` (pending→building→success|failed) y sus campos de resultado. El
 * `published_hash` da idempotencia (mismo estado publicado ⇒ no reconstruye).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deployments', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->string('target', 20)->default('static'); // static (dynamic/SSG: futuro)
            $table->string('status', 20)->default('pending'); // pending|building|success|failed
            $table->string('published_hash', 64); // sha256 del estado publicado del sitio
            $table->string('artifact_ref')->nullable(); // ruta del ZIP en el disk (al terminar)
            $table->unsignedBigInteger('bytes')->nullable(); // tamaño del artefacto
            $table->text('error')->nullable(); // mensaje si status=failed
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Historial por sitio (más reciente primero). Inicia por workspace_id (convención).
            $table->index(['workspace_id', 'site_id', 'created_at'], 'deployments_history_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deployments');
    }
};
