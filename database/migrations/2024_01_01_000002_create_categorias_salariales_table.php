<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ═══════════════════════════════════════════════════════════
     * TABLA: CATEGORÍAS SALARIALES
     * ═══════════════════════════════════════════════════════════
     * Define las categorías A, B, C para el pago de músicos
     */
    public function up(): void
    {
        Schema::create('categorias_salariales', function (Blueprint $table) {
            $table->id();
            $table->char('codigo', 1)->unique(); // A, B, C
            $table->string('nombre', 50);
            $table->text('descripcion')->nullable();
            $table->decimal('monto_base', 10, 2)->default(0); // Monto base por defecto
            $table->integer('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias_salariales');
    }
};
