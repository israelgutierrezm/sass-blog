<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menús de navegación por-sitio (ADR-017). `handle` libre (primary|footer|…), único
 * por sitio. Los ítems (jerárquicos) viven en `menu_items`. Los enlaces se resuelven
 * en render, no se congela el path.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->string('handle', 50); // primary | footer | … (libre, no enum cerrado)
            $table->string('name');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Un handle por sitio; el prefijo (ws,site) sirve también para listar.
            $table->unique(['workspace_id', 'site_id', 'handle']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
