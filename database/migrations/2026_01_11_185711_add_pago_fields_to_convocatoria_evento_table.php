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
            $table->boolean('pagado')->default(false);
            $table->timestamp('fecha_pago')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('convocatoria_evento', function (Blueprint $table) {
            $table->dropColumn(['pagado', 'fecha_pago']);
        });
    }
};
