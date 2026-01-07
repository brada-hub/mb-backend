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
        Schema::create('audios', function (Blueprint $table) {
            $table->id('id_audio');
            $table->string('url_audio');
            $table->string('tipo_entidad'); // 'TEMA', 'MIX'
            $table->unsignedBigInteger('id_entidad');
            $table->timestamps();

            // Index for faster polymorphic lookups
            $table->index(['tipo_entidad', 'id_entidad']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audios');
    }
};
