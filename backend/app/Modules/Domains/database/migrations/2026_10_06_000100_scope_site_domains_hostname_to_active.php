<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verificación de PROPIEDAD de dominios (ADR-025, reemplaza la verificación por apuntado de
 * ADR-020).
 *
 * - El hostname deja de ser UNIQUE desde la creación: una reclamación sin verificar ya no bloquea
 *   al dueño real. La regla «un dominio ACTIVO → un sitio» la garantiza la columna generada
 *   `active_hostname` (= hostname sólo si status = active; los NULL no colisionan) con UNIQUE.
 *   Ese índice sirve también a `resolve`/`tls-check`.
 * - UNIQUE (workspace_id, site_id, hostname): un sitio no reclama dos veces el mismo hostname. Su
 *   prefijo cubre el listado scopeado y la FK de workspace_id → sustituye a site_domains_site_idx.
 * - `failure_reason` (ownership|routing|taken): por qué falló la última verificación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_domains', function (Blueprint $table) {
            $table->string('active_hostname')->nullable()
                ->storedAs("IF(`status` = 'active', `hostname`, NULL)")
                ->after('hostname');
            $table->string('failure_reason', 20)->nullable()->after('status'); // ownership|routing|taken
        });

        // Primero los índices nuevos: el de (workspace_id, …) debe existir antes de quitar
        // site_domains_site_idx, que hoy sostiene la FK de workspace_id.
        Schema::table('site_domains', function (Blueprint $table) {
            $table->unique('active_hostname', 'site_domains_active_hostname_unique');
            $table->unique(['workspace_id', 'site_id', 'hostname'], 'site_domains_site_hostname_unique');
        });

        Schema::table('site_domains', function (Blueprint $table) {
            $table->dropUnique('site_domains_hostname_unique');
            $table->dropIndex('site_domains_site_idx');
        });
    }

    public function down(): void
    {
        // Falla si hay hostnames repetidos (reclamaciones coexistentes): hay que depurarlos antes.
        Schema::table('site_domains', function (Blueprint $table) {
            $table->index(['workspace_id', 'site_id'], 'site_domains_site_idx');
            $table->unique('hostname', 'site_domains_hostname_unique');
        });

        Schema::table('site_domains', function (Blueprint $table) {
            $table->dropUnique('site_domains_site_hostname_unique');
            $table->dropUnique('site_domains_active_hostname_unique');
            $table->dropColumn(['active_hostname', 'failure_reason']);
        });
    }
};
