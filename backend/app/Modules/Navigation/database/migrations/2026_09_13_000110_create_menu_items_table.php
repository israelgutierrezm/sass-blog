<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ítems de menú jerárquicos (ADR-017): adjacency list (`parent_id` + `position`),
 * 2–3 niveles. Cada ítem guarda una REFERENCIA (`link_type` + `target_ulid`/`url`),
 * no el path: se resuelve en render (respeta el slug history). Nested-set sería
 * sobre-ingeniería para esta profundidad (CLAUDE.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignId('menu_id')->constrained('menus')->cascadeOnDelete();
            // Autorreferencia para la jerarquía; borrar un padre arrastra sus hijos.
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->string('label');
            $table->string('link_type', 20); // page | entry | collection | url | home
            $table->char('target_ulid', 26)->nullable(); // ULID de page/entry/collection
            $table->string('url')->nullable(); // sólo link_type = url
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            // Camino caliente: leer el árbol de un menú ordenado por nivel/posición.
            // Inicia por workspace_id (convención de índices compuestos, CLAUDE.md).
            $table->index(['workspace_id', 'menu_id', 'parent_id', 'position'], 'menu_items_tree_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
