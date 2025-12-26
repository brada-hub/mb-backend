<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ═══════════════════════════════════════════════════════════
     * TABLAS: SISTEMA CONTABLE Y PAGOS
     * ═══════════════════════════════════════════════════════════
     */
    public function up(): void
    {
        // ─── Tarifario por Sección y Categoría ───
        Schema::create('tarifas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seccion_id')->constrained('secciones');
            $table->foreignId('categoria_id')->constrained('categorias_salariales');
            $table->decimal('monto_ensayo', 10, 2)->default(0);
            $table->decimal('monto_contrato', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['seccion_id', 'categoria_id']);
        });

        // ─── Liquidaciones por Evento ───
        Schema::create('liquidaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventos');
            $table->foreignId('miembro_id')->constrained('miembros');

            // ─── Montos ───
            $table->decimal('monto_base', 10, 2);
            $table->decimal('descuento_tardanza', 10, 2)->default(0);
            $table->decimal('otros_descuentos', 10, 2)->default(0);
            $table->decimal('bonificacion', 10, 2)->default(0);
            $table->decimal('monto_final', 10, 2);

            // ─── Estado ───
            $table->enum('estado', ['pendiente', 'pagado', 'parcial'])->default('pendiente');
            $table->text('observaciones')->nullable();

            $table->timestamps();

            $table->unique(['evento_id', 'miembro_id']);
        });

        // ─── Registro de Pagos ───
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('miembro_id')->constrained('miembros');

            // ─── Información del Pago ───
            $table->decimal('monto', 12, 2);
            $table->enum('metodo', ['efectivo', 'transferencia', 'qr'])->default('efectivo');
            $table->string('referencia', 100)->nullable(); // Nro de transferencia, etc.

            // ─── Período ───
            $table->date('fecha_pago');
            $table->date('periodo_inicio')->nullable();
            $table->date('periodo_fin')->nullable();

            // ─── Estado ───
            $table->enum('estado', ['procesado', 'anulado'])->default('procesado');
            $table->text('observaciones')->nullable();

            // ─── Auditoría ───
            $table->foreignId('registrado_por')->constrained('miembros');

            $table->timestamps();
        });

        // ─── Detalle de Pagos (qué liquidaciones cubre) ───
        Schema::create('pago_liquidaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pago_id')->constrained('pagos')->onDelete('cascade');
            $table->foreignId('liquidacion_id')->constrained('liquidaciones');
            $table->decimal('monto_aplicado', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pago_liquidaciones');
        Schema::dropIfExists('pagos');
        Schema::dropIfExists('liquidaciones');
        Schema::dropIfExists('tarifas');
    }
};
