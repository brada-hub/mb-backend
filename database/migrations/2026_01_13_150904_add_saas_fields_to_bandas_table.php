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
        Schema::table('bandas', function (Blueprint $table) {
            $table->string('plan')->default('BASIC'); // BASIC, PREMIUM, PRO
            $table->date('fecha_vencimiento')->nullable();
            $table->integer('max_miembros')->default(15);
            $table->boolean('notificaciones_habilitadas')->default(false);
            $table->decimal('cuota_mensual', 10, 2)->default(0.00);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bandas', function (Blueprint $table) {
            $table->dropColumn(['plan', 'fecha_vencimiento', 'max_miembros', 'notificaciones_habilitadas', 'cuota_mensual']);
        });
    }
};
