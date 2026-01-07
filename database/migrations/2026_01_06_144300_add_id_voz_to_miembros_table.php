<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('miembros', function (Blueprint $table) {
            $table->unsignedBigInteger('id_voz')->nullable()->after('id_instrumento');
            $table->foreign('id_voz')->references('id_voz')->on('voces_instrumentales')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('miembros', function (Blueprint $table) {
            $table->dropForeign(['id_voz']);
            $table->dropColumn('id_voz');
        });
    }
};
