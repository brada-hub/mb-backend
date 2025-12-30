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
        Schema::create('contactos_referencia', function (Blueprint $table) {
            $table->id('id_contacto');
            $table->foreignId('id_miembro')->constrained('miembros', 'id_miembro')->onDelete('cascade');
            $table->string('nombres_apellidos', 100);
            $table->string('parentesco', 50)->nullable();
            $table->integer('celular');
            $table->timestamp('fecha_registro')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contactos_referencia');
    }
};
