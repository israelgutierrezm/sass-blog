<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ALTER pages para plantillas de colección (ADR-011): `kind` distingue páginas
 * normales de plantillas de detalle; las plantillas NO se direccionan por `path`
 * (nullable). Un CHECK garantiza la invariante a nivel de BD (además de la guarda
 * de modelo y el Form Request). Las filas existentes son kind='standard' con path
 * no nulo → cumplen el CHECK.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->string('kind', 30)->default('standard')->after('site_id');
            $table->string('path')->nullable()->change();
            $table->index(['workspace_id', 'site_id', 'kind']);
        });

        DB::statement(
            'ALTER TABLE pages ADD CONSTRAINT pages_kind_path_chk CHECK ('
            ."(kind = 'standard' AND path IS NOT NULL) OR (kind = 'collection_template' AND path IS NULL))"
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE pages DROP CONSTRAINT pages_kind_path_chk');

        Schema::table('pages', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'site_id', 'kind']);
            $table->dropColumn('kind');
            // Revierte path a NOT NULL (asume que no quedan plantillas al hacer rollback).
            $table->string('path')->nullable(false)->change();
        });
    }
};
