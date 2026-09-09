<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assets de la librería de medios, por-sitio (ADR-016). `disk`+`path` localizan el
 * binario en el Filesystem (local en dev, S3-compat en prod). `checksum` (sha256) da
 * dedup dentro del sitio. `status` pasa de processing a ready cuando el job genera las
 * variantes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->string('disk', 40);
            $table->string('path');
            $table->string('original_filename');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt')->nullable();
            $table->string('title')->nullable();
            $table->char('checksum', 64); // sha256 hex
            $table->string('status', 20)->default('processing'); // processing | ready
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workspace_id', 'site_id', 'created_at']);
            // Dedup por-sitio: un mismo binario no se guarda dos veces en el sitio.
            $table->unique(['workspace_id', 'site_id', 'checksum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};
