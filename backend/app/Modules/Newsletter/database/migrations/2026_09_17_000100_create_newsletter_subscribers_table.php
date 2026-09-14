<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suscriptores de newsletter por-sitio (ADR-022). Doble opt-in: `pending` → `confirmed`
 * (por `confirmation_token`) → `unsubscribed` (por `unsubscribe_token`). El email es único
 * por sitio; los tokens son únicos globales porque los resuelven endpoints públicos sin
 * contexto de workspace.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->string('email');
            $table->string('status', 20)->default('pending'); // pending|confirmed|unsubscribed
            $table->char('confirmation_token', 64)->unique();
            $table->char('unsubscribe_token', 64)->unique();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'email'], 'newsletter_subscribers_site_email_unique');
            $table->index(['workspace_id', 'site_id', 'status'], 'newsletter_subscribers_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscribers');
    }
};
