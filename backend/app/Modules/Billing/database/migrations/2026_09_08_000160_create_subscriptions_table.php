<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suscripción del workspace a un plan. Fase 1: un workspace, una suscripción.
 *
 * Es el puente que resuelve las capabilities del workspace: subscription → plan →
 * plan_capabilities. Scopeada por workspace_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->string('status')->default('active'); // trialing|active|past_due|canceled
            $table->timestamp('current_period_end')->nullable();
            $table->timestamps();

            // Un workspace tiene exactamente una suscripción (Fase 1).
            $table->unique('workspace_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
