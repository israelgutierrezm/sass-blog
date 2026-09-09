<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Redirects por-sitio (ADR-018). Unifican los manuales y los automáticos por cambio
 * de slug (`source`). El `/render` los consulta ANTES del 404 y Nuxt emite el 3xx.
 * `from_path` es único por sitio (una ruta origen no puede apuntar a dos destinos).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->string('from_path');
            $table->string('to_path');
            $table->unsignedSmallInteger('status')->default(301); // 301 | 302
            $table->string('source', 20)->default('manual'); // manual | slug_change
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['workspace_id', 'site_id', 'from_path']);
            $table->index(['workspace_id', 'site_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redirects');
    }
};
