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
        Schema::create('bandas', function (Blueprint $table) {
            $table->id('id_banda');
            $table->string('nombre');
            $table->string('slug')->unique(); // Para la URL personalizada
            $table->string('logo')->nullable();
            $table->string('color_primario')->default('#6366f1');
            $table->string('color_secundario')->default('#161b2c');
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bandas');
    }
};
