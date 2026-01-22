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
        $tables = [
            'users',
            'miembros',
            'eventos',
            'tipos_evento',
            'roles',
            'secciones',
            'instrumentos',
            'generos',
            'temas',
            'mixes',
            'notificaciones'
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                // PostgreSQL no soporta 'after' en migraciones
                $table->unsignedBigInteger('id_banda')->nullable();
                $table->foreign('id_banda')->references('id_banda')->on('bandas')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'notificaciones',
            'mixes',
            'temas',
            'generos',
            'instrumentos',
            'secciones',
            'roles',
            'tipos_evento',
            'eventos',
            'miembros',
            'users'
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['id_banda']);
                $table->dropColumn('id_banda');
            });
        }
    }
};
