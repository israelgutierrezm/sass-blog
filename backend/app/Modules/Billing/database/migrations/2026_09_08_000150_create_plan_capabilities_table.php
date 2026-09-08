<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qué capabilities otorga cada plan, con un límite opcional (cuota).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_capabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->foreignId('capability_id')->constrained('capabilities')->cascadeOnDelete();
            // null = otorgada sin límite; entero = cuota (p.ej. nº de sitios).
            $table->unsignedInteger('limit')->nullable();
            $table->timestamps();

            $table->unique(['plan_id', 'capability_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_capabilities');
    }
};
