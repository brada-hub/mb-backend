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
        Schema::create('uniforme_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('uniforme_id');
            $table->string('tipo'); // camisa, pantalon, saco, corbata, zapatos
            $table->string('color')->default('#000000');
            $table->string('detalle')->nullable();
            $table->foreign('uniforme_id')->references('id')->on('uniformes')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('uniforme_items');
    }
};
