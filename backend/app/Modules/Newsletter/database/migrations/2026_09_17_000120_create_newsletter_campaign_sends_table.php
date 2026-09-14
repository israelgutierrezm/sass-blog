<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de envío por-destinatario (ADR-022). Da IDEMPOTENCIA al envío de una campaña:
 * la unique (campaign_id, subscriber_id) hace que re-ejecutar el job salte a quien ya recibió.
 * Guarda enviado/fallido; el tracking de aperturas/clics queda fuera del MVP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_campaign_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained('newsletter_campaigns')->cascadeOnDelete();
            $table->foreignId('subscriber_id')->constrained('newsletter_subscribers')->cascadeOnDelete();
            $table->string('status', 20); // sent|failed
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'subscriber_id'], 'newsletter_campaign_sends_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_campaign_sends');
    }
};
