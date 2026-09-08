<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Capabilities: catálogo CERRADO de funcionalidades del plan (global, no scopeado).
 *
 * La fuente de verdad es el enum Capability del kernel; un seeder versionado
 * refleja el enum en esta tabla para dar integridad referencial a plan_capabilities.
 * Responde "¿el plan permite la funcionalidad?", NO "¿el usuario puede la acción?"
 * (eso es RBAC).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capabilities', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // site.custom_domain, cms.collections ...
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capabilities');
    }
};
