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
        Schema::table('convocatoria_evento', function (Blueprint $table) {
            $table->boolean('confirmado_por_miembro')->nullable()->after('confirmado_por_director');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('convocatoria_evento', function (Blueprint $table) {
            $table->dropColumn('confirmado_por_miembro');
        });
    }
};
