<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dominios propios por-sitio (ADR-020). El `hostname` es ÚNICO en toda la plataforma
 * (un dominio → un sitio). Enrutado + máquina de estados de verificación; el dominio NO
 * se acopla a la Page. El TLS lo resuelve el edge (Caddy on-demand), no esta tabla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_domains', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->string('hostname')->unique(); // único GLOBAL (un dominio → un sitio)
            $table->string('status', 20)->default('pending');    // pending|verifying|active|failed
            $table->string('ssl_status', 20)->default('none');   // none|provisioning|active|failed
            $table->string('verification_token', 64);
            $table->timestamp('verified_at')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['workspace_id', 'site_id'], 'site_domains_site_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_domains');
    }
};
