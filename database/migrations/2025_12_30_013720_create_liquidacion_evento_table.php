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
        Schema::create('liquidacion_evento', function (Blueprint $table) {
            $table->id('id_liquidacion');
            $table->foreignId('id_evento')->constrained('eventos', 'id_evento')->onDelete('cascade');
            $table->decimal('total_pagado_musicos', 12, 2)->default(0);
            $table->decimal('total_multas_recaudada', 12, 2)->default(0);
            $table->decimal('utilidad_neta', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('liquidacion_evento');
    }
};
