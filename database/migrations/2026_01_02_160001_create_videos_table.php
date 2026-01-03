<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('videos', function (Blueprint $table) {
            $table->id('id_video');
            $table->string('url_video');
            $table->string('titulo')->nullable();
            $table->unsignedBigInteger('id_tema');
            $table->timestamps();

            $table->foreign('id_tema')->references('id_tema')->on('temas')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('videos');
    }
};
