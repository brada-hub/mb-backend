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
        // 1. Agregar fcm_token a dispositivos_autorizados
        Schema::table('dispositivos_autorizados', function (Blueprint $table) {
            if (!Schema::hasColumn('dispositivos_autorizados', 'fcm_token')) {
                $table->text('fcm_token')->nullable();
            }
        });

        // 2. Limite de dispositivos ya existe en users, pero nos aseguramos
        if (!Schema::hasColumn('users', 'limite_dispositivos')) {
            Schema::table('users', function (Blueprint $table) {
                $table->integer('limite_dispositivos')->default(2);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dispositivos_autorizados', function (Blueprint $table) {
            $table->dropColumn('fcm_token');
        });
    }
};
