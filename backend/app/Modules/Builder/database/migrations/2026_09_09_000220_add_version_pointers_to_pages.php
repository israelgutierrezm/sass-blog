<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cierra la dependencia circular pages <-> page_versions: añade las FK reales de
 * los punteros de pages hacia page_versions (nullOnDelete). El down() hace
 * dropForeign ANTES de que se reviertan las tablas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->foreign('draft_version_id')->references('id')->on('page_versions')->nullOnDelete();
            $table->foreign('published_version_id')->references('id')->on('page_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropForeign(['draft_version_id']);
            $table->dropForeign(['published_version_id']);
        });
    }
};
