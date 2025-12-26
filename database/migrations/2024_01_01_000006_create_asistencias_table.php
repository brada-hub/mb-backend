<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ═══════════════════════════════════════════════════════════
     * TABLA: ASISTENCIAS
     * ═══════════════════════════════════════════════════════════
     * Control de asistencia con geolocalización
     */
    public function up(): void
    {
        Schema::create('asistencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventos')->onDelete('cascade');
            $table->foreignId('miembro_id')->constrained('miembros');

            // ─── Registro de Llegada ───
            $table->timestamp('hora_llegada')->nullable();
            $table->decimal('latitud_llegada', 10, 8)->nullable();
            $table->decimal('longitud_llegada', 11, 8)->nullable();

            // ─── Estado de Asistencia ───
            $table->enum('estado', [
                'pendiente',      // Aún no ha llegado
                'a_tiempo',       // Llegó dentro de tolerancia
                'tarde',          // Llegó tarde
                'ausente',        // No se presentó
                'justificado'     // Falta justificada
            ])->default('pendiente');

            // ─── Cálculo de Retraso ───
            $table->integer('minutos_retraso')->default(0);
            $table->decimal('descuento', 10, 2)->default(0);

            // ─── Registro Manual ───
            $table->boolean('registro_manual')->default(false);
            $table->foreignId('registrado_por')->nullable()->constrained('miembros');
            $table->text('observaciones')->nullable();

            // ─── Justificación ───
            $table->text('justificacion')->nullable();
            $table->foreignId('justificado_por')->nullable()->constrained('miembros');

            $table->timestamps();

            $table->unique(['evento_id', 'miembro_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asistencias');
    }
};
