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
        Schema::create('asistencias', function (Blueprint $table) {
            $table->id('id_asistencia');
            $table->foreignId('id_evento')->constrained('eventos', 'id_evento')->onDelete('cascade');
            $table->foreignId('id_miembro')->constrained('miembros', 'id_miembro')->onDelete('cascade');
            $table->time('hora_llegada')->nullable();
            $table->integer('minutos_retraso')->default(0);
            $table->string('estado')->default('PENDIENTE');
            $table->string('offline_uuid')->nullable()->unique();
            $table->decimal('latitud_marcado', 10, 8)->nullable();
            $table->decimal('longitud_marcado', 11, 8)->nullable();
            $table->timestamp('fecha_sincronizacion')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asistencias');
    }
};
