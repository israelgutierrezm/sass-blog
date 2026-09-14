<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rollup diario de analítica (ADR-021). Una fila por (sitio, día, ruta) con `views` y
 * `visitors` (únicos por hash/día). Fuente del dashboard: pequeño y permanente. El job de
 * rollup hace UPSERT sobre la unique, por lo que re-ejecutarlo es idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->date('stat_date');
            $table->string('path');
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('visitors')->default(0);
            $table->timestamps();

            // UPSERT idempotente del rollup (una fila por sitio/día/ruta).
            $table->unique(['site_id', 'stat_date', 'path'], 'analytics_daily_unique');
            // Consulta por rango del dashboard, por tenant/sitio.
            $table->index(['workspace_id', 'site_id', 'stat_date'], 'analytics_daily_range_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_daily_stats');
    }
};
