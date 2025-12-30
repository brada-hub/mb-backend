<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('miembro_permiso', function (Blueprint $table) {
            $table->id('id_miembro_permiso');
            $table->foreignId('id_miembro')->constrained('miembros', 'id_miembro')->onDelete('cascade');
            $table->foreignId('id_permiso')->constrained('permisos', 'id_permiso')->onDelete('cascade');
            $table->boolean('estado_booleano')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('miembro_permiso');
    }
};
