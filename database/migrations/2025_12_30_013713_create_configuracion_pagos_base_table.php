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
        Schema::create('configuracion_pagos_base', function (Blueprint $table) {
            $table->id('id_pago_config');
            $table->foreignId('id_categoria')->constrained('categorias', 'id_categoria');
            $table->foreignId('id_seccion')->constrained('secciones', 'id_seccion');
            $table->decimal('monto_base_estandar', 10, 2);
            $table->decimal('bono_instrumento', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configuracion_pagos_base');
    }
};
