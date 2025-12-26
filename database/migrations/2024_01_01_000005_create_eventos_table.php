<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ═══════════════════════════════════════════════════════════
     * TABLA: EVENTOS (Ensayos y Contratos)
     * ═══════════════════════════════════════════════════════════
     */
    public function up(): void
    {
        Schema::create('eventos', function (Blueprint $table) {
            $table->id();

            // ─── Información Básica ───
            $table->string('nombre', 200);
            $table->enum('tipo', ['ensayo', 'contrato'])->default('ensayo');
            $table->text('descripcion')->nullable();

            // ─── Fecha y Hora ───
            $table->date('fecha');
            $table->time('hora_citacion');
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fin')->nullable();

            // ─── Ubicación ───
            $table->string('lugar', 200)->nullable();
            $table->text('direccion')->nullable();
            $table->decimal('latitud', 10, 8);
            $table->decimal('longitud', 11, 8);
            $table->integer('radio_geofence')->default(50); // Radio en metros para geolocalización

            // ─── Configuración de Asistencia ───
            $table->integer('tolerancia_minutos')->default(30);
            $table->decimal('descuento_por_minuto', 8, 2)->default(0);

            // ─── Económico (solo para contratos) ───
            $table->decimal('monto_total', 12, 2)->nullable();
            $table->string('cliente', 200)->nullable();
            $table->string('cliente_celular', 20)->nullable();

            // ─── Estado ───
            $table->enum('estado', ['borrador', 'confirmado', 'en_curso', 'finalizado', 'cancelado'])->default('borrador');
            $table->boolean('lista_confirmada')->default(false);
            $table->timestamp('fecha_confirmacion_lista')->nullable();

            // ─── Auditoría ───
            $table->foreignId('creado_por')->nullable()->constrained('miembros');

            $table->timestamps();
            $table->softDeletes();
        });

        // ─── Cupos por Sección para cada Evento ───
        Schema::create('evento_cupos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventos')->onDelete('cascade');
            $table->foreignId('seccion_id')->constrained('secciones');
            $table->integer('cantidad')->default(0);
            $table->timestamps();

            $table->unique(['evento_id', 'seccion_id']);
        });

        // ─── Lista de Miembros Seleccionados ───
        Schema::create('evento_miembros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventos')->onDelete('cascade');
            $table->foreignId('miembro_id')->constrained('miembros');
            $table->foreignId('seccion_id')->constrained('secciones');

            // ─── Estado de selección ───
            $table->enum('estado', ['propuesto', 'confirmado', 'rechazado'])->default('propuesto');
            $table->foreignId('propuesto_por')->nullable()->constrained('miembros');
            $table->foreignId('confirmado_por')->nullable()->constrained('miembros');
            $table->timestamp('fecha_notificacion')->nullable();
            $table->boolean('notificado')->default(false);

            $table->timestamps();

            $table->unique(['evento_id', 'miembro_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evento_miembros');
        Schema::dropIfExists('evento_cupos');
        Schema::dropIfExists('eventos');
    }
};
