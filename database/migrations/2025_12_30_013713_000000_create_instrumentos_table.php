<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('instrumentos', function (Blueprint $table) {
            $table->id('id_instrumento');
            $table->string('instrumento', 100);
            $table->unsignedBigInteger('id_seccion');
            $table->timestamps();

            $table->foreign('id_seccion')->references('id_seccion')->on('secciones')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('instrumentos');
    }
};
