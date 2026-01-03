<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Renombramos la tabla a 'recursos' desde el inicio para evitar confusiones
        Schema::create('recursos', function (Blueprint $table) {
            $table->id('id_recurso');
            // Relación con Instrumentos (nueva estructura)
            // Asumimos que la tabla instrumentos ya se creó (movimos su migración antes de esta)
            $table->unsignedBigInteger('id_instrumento');

            $table->foreignId('id_tema')->constrained('temas', 'id_tema');
            $table->foreignId('id_voz')->constrained('voces_instrumentales', 'id_voz');

            // Campos de auditoría
            $table->timestamps();

            // Definición de FK manual para instrumentos
            $table->foreign('id_instrumento')->references('id_instrumento')->on('instrumentos')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recursos');
    }
};
