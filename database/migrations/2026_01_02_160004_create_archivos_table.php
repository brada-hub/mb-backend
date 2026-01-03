<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('archivos', function (Blueprint $table) {
            $table->id('id_archivo');
            $table->string('url_archivo');
            $table->string('tipo', 50)->nullable(); // 'pdf', 'image', etc.
            $table->string('nombre_original')->nullable();
            $table->integer('orden')->default(0);
            $table->unsignedBigInteger('id_recurso');
            $table->timestamps();

            $table->foreign('id_recurso')->references('id_recurso')->on('recursos')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('archivos');
    }
};
