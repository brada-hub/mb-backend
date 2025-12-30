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
        Schema::create('eventos', function (Blueprint $table) {
            $table->id('id_evento');
            $table->foreignId('id_tipo_evento')->constrained('tipos_evento', 'id_tipo_evento');
            $table->string('evento');
            $table->date('fecha');
            $table->time('hora');
            $table->decimal('latitud', 10, 8)->nullable();
            $table->decimal('longitud', 11, 8)->nullable();
            $table->string('direccion')->nullable();
            $table->integer('radio')->default(100);
            $table->boolean('estado')->default(true);
            $table->decimal('ingreso_total_contrato', 12, 2)->default(0);
            $table->decimal('presupuesto_limite_sueldos', 12, 2)->default(0);
            $table->integer('version_evento')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eventos');
    }
};
