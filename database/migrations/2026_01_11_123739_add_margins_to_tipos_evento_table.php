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
        Schema::table('tipos_evento', function (Blueprint $table) {
            $table->integer('minutos_antes_marcar')->default(15);
            $table->integer('horas_despues_sellar')->default(24);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tipos_evento', function (Blueprint $table) {
            $table->dropColumn(['minutos_antes_marcar', 'horas_despues_sellar']);
        });
    }
};
