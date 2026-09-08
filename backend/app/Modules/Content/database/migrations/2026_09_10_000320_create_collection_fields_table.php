<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos personalizados de una colección: el schema contra el que se valida
 * `entries.data` (ADR-009/010). `config` es descriptor por tipo (JSON sancionado,
 * como props/settings de secciones); el único eje relacional (target de un campo
 * `relation`) va en columna FK real `related_collection_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_fields', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignId('collection_id')->constrained('collections')->cascadeOnDelete();
            $table->string('key'); // clave dentro de entries.data y raíz del binding
            $table->string('label');
            $table->string('type', 20); // FieldType
            $table->boolean('required')->default(false);
            $table->json('config')->nullable();
            $table->foreignId('related_collection_id')->nullable()->constrained('collections')->restrictOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['collection_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_fields');
    }
};
