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
        Schema::create('detalle_formaciones', function (Blueprint $table) {
            $table->id('id_detalle_formacion');
            $table->unsignedBigInteger('id_formacion');
            $table->unsignedBigInteger('id_miembro');
            $table->timestamps();

            $table->foreign('id_formacion')->references('id_formacion')->on('formaciones')->onDelete('cascade');
            $table->foreign('id_miembro')->references('id_miembro')->on('miembros')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detalle_formaciones');
    }
};
