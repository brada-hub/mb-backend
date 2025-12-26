<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ═══════════════════════════════════════════════════════════
     * TABLA: SECCIONES MUSICALES
     * ═══════════════════════════════════════════════════════════
     * Almacena las secciones de la banda:
     * - Platillos, Tambores, Bombos, Trompetas, Trombones,
     * - Clarinetes, Bajos/Barítonos, Helicones/Tubas
     */
    public function up(): void
    {
        Schema::create('secciones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('nombre_corto', 20)->nullable();
            $table->string('icono', 50)->nullable(); // Nombre del icono para UI
            $table->string('color', 7)->default('#6366f1'); // Color hex para UI
            $table->text('descripcion')->nullable();
            $table->boolean('es_viento')->default(false); // Para determinar si usa partituras
            $table->integer('orden')->default(0); // Orden de visualización
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secciones');
    }
};
