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
        Schema::table('eventos', function (Blueprint $table) {
            // Optimizar búsquedas por fecha y banda (Dashboard/Calendario)
            $table->index(['id_banda', 'fecha', 'hora'], 'idx_eventos_banda_fecha');
            $table->index('fecha', 'idx_eventos_fecha');
        });

        Schema::table('convocatoria_evento', function (Blueprint $table) {
            // Optimizar búsqueda de convocatorias por miembro y evento
            $table->index(['id_evento', 'id_miembro'], 'idx_convocatoria_evento_miembro');
            $table->index('id_miembro', 'idx_convocatoria_miembro_only');
            $table->index(['id_evento', 'confirmado_por_director'], 'idx_convocatoria_confirmados');
        });

        Schema::table('asistencias', function (Blueprint $table) {
            // Optimizar estadísticas y reportes
            $table->index(['id_convocatoria', 'estado'], 'idx_asistencias_estado');
            $table->index('fecha_sincronizacion', 'idx_asistencias_sync');
        });

        Schema::table('miembros', function (Blueprint $table) {
            // Optimizar filtros por sección/instrumento
            $table->index(['id_banda', 'id_instrumento'], 'idx_miembros_banda_inst');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropIndex('idx_eventos_banda_fecha');
            $table->dropIndex('idx_eventos_fecha');
        });

        Schema::table('convocatoria_evento', function (Blueprint $table) {
            $table->dropIndex('idx_convocatoria_evento_miembro');
            $table->dropIndex('idx_convocatoria_miembro_only');
            $table->dropIndex('idx_convocatoria_confirmados');
        });

        Schema::table('asistencias', function (Blueprint $table) {
            $table->dropIndex('idx_asistencias_estado');
            $table->dropIndex('idx_asistencias_sync');
        });

        Schema::table('miembros', function (Blueprint $table) {
            $table->dropIndex('idx_miembros_banda_inst');
        });
    }
};
