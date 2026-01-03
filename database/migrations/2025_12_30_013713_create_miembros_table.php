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
        Schema::create('miembros', function (Blueprint $table) {
            $table->id('id_miembro');
            $table->foreignId('id_categoria')->constrained('categorias', 'id_categoria');
            $table->foreignId('id_seccion')->constrained('secciones', 'id_seccion');
            $table->foreignId('id_rol')->constrained('roles', 'id_rol');
            $table->string('nombres', 50);
            $table->string('apellidos', 50);
            $table->string('ci')->unique();
            $table->integer('celular');
            $table->date('fecha')->nullable();
            $table->decimal('latitud', 10, 8)->nullable();
            $table->decimal('longitud', 11, 8)->nullable();
            $table->string('direccion')->nullable();
            $table->unsignedBigInteger('id_instrumento')->nullable(); // Nueva estructura
            $table->integer('version_perfil')->default(1); // Offline sync field
            $table->timestamps();

            $table->foreign('id_instrumento')->references('id_instrumento')->on('instrumentos')->onDelete('set null');
        });

        // Ahora que miembros existe, conectamos users
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('id_miembro')->references('id_miembro')->on('miembros')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['id_miembro']);
        });
        Schema::dropIfExists('miembros');
    }
};
