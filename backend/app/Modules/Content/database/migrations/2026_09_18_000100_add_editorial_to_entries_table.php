<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flujo editorial en las entradas (ADR-023). Los estados nuevos (in_review, scheduled) NO
 * tocan el esquema (`status` es string); sólo se añade `editorial_note` (feedback de "pedir
 * cambios") y un índice para el barrido del job de publicación programada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->text('editorial_note')->nullable()->after('status');
            // Índice de PLATAFORMA (cross-tenant): el job de programación barre
            // `status=scheduled AND published_at<=now` en todos los workspaces. Por eso NO
            // empieza por workspace_id (excepción documentada del job, ADR-023).
            $table->index(['status', 'published_at'], 'entries_workflow_idx');
        });
    }

    public function down(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->dropIndex('entries_workflow_idx');
            $table->dropColumn('editorial_note');
        });
    }
};
