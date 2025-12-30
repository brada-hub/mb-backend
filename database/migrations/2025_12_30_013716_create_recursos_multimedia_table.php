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
        Schema::create('recursos_multimedia', function (Blueprint $table) {
            $table->id('id_recurso');
            $table->foreignId('id_seccion')->constrained('secciones', 'id_seccion');
            $table->foreignId('id_tema')->constrained('temas', 'id_tema');
            $table->foreignId('id_voz')->constrained('voces_instrumentales', 'id_voz');
            $table->text('archivo_url');
            $table->text('audio_guia_url_opcional')->nullable();
            $table->string('tipo_recurso')->nullable();
            $table->string('hash_archivo')->nullable();
            $table->bigInteger('tamano_archivo_bytes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recursos_multimedia');
    }
};
