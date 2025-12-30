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
        Schema::create('requerimiento_seccion', function (Blueprint $table) {
            $table->id('id_requerimiento');
            $table->foreignId('id_evento')->constrained('eventos', 'id_evento')->onDelete('cascade');
            $table->foreignId('id_seccion')->constrained('secciones', 'id_seccion');
            $table->integer('cantidad_necesaria');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requerimiento_seccion');
    }
};
