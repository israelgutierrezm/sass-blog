<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivote M:N entrada↔categoría (sin modelo Eloquent; se gestiona por belongsToMany).
 * Lleva workspace_id/site_id por doctrina ("tenant en toda tabla de dominio"); NO
 * tiene global scope (no es modelo) → se accede sólo vía Entry/Category ya
 * scopeados, y el aislamiento se cubre con test por-site.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_entry', function (Blueprint $table) {
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->foreignId('entry_id')->constrained('entries')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['category_id', 'entry_id']);
            $table->index(['workspace_id', 'site_id', 'entry_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_entry');
    }
};
