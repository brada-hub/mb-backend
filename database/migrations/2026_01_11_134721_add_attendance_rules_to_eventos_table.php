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
            $table->integer('minutos_tolerancia')->default(15);
            $table->integer('minutos_cierre')->default(60);
        });

        Schema::table('eventos', function (Blueprint $table) {
            $table->integer('minutos_tolerancia')->nullable(); // Nullable to use type defaults
            $table->integer('minutos_cierre')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tipos_evento', function (Blueprint $table) {
            $table->dropColumn(['minutos_tolerancia', 'minutos_cierre']);
        });

        Schema::table('eventos', function (Blueprint $table) {
            $table->dropColumn(['minutos_tolerancia', 'minutos_cierre']);
        });
    }
};
