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
        Schema::create('uniformes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('banda_id');
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->foreign('banda_id')->references('id_banda')->on('bandas')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('uniformes');
    }
};
