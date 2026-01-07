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
        Schema::create('detalle_mixes', function (Blueprint $table) {
            $table->id('id_detalle_mix');
            $table->foreignId('id_mix')->constrained('mixes', 'id_mix')->onDelete('cascade');
            $table->foreignId('id_tema')->constrained('temas', 'id_tema')->onDelete('cascade');
            $table->integer('orden')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detalle_mixes');
    }
};
