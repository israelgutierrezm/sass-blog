<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Derivados de un asset de imagen (thumb/medium/large) generados por el job
 * (ADR-016). Hijos del asset (cascade). `workspace_id`/`site_id` denormalizados para
 * el WorkspaceScope, como el resto de tablas de dominio. Sin ULID: no se exponen por
 * API salvo como URLs dentro del Resource del asset.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignId('media_asset_id')->constrained('media_assets')->cascadeOnDelete();
            $table->string('variant', 20); // thumb | medium | large
            $table->string('disk', 40);
            $table->string('path');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->timestamps();

            $table->unique(['media_asset_id', 'variant']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_variants');
    }
};
