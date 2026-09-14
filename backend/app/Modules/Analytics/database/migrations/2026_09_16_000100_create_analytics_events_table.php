<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eventos de pageview crudos (ADR-021). Append-only e inmutables: una fila por render
 * público. Sin PII — el visitante se representa por `visitor_hash` (HMAC diario, ver
 * VisitorHasher). Se agregan a `analytics_daily_stats` por job y se podan por retención.
 *
 * Sin `timestamps`: `occurred_at` ES el tiempo del evento; ahorra dos columnas en una
 * tabla de alto volumen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->string('path'); // ruta pública (acotada a 255 para caber en el índice del rollup)
            $table->timestamp('occurred_at');
            $table->string('referrer_host')->nullable(); // sólo host, sin query (privacidad)
            $table->char('visitor_hash', 64);            // HMAC sha256 hex; NO reversible, sin IP
            $table->boolean('is_bot')->default(false);

            // Barrido del rollup y poda por rango, por tenant/sitio.
            $table->index(['workspace_id', 'site_id', 'occurred_at'], 'analytics_events_scan_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};
