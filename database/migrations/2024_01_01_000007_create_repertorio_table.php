<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ═══════════════════════════════════════════════════════════
     * TABLAS: REPERTORIO MUSICAL
     * ═══════════════════════════════════════════════════════════
     * Géneros → Temas → Partituras por Sección
     */
    public function up(): void
    {
        // ─── Géneros Musicales ───
        Schema::create('generos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('slug', 100)->unique();
            $table->text('descripcion')->nullable();
            $table->string('icono', 50)->nullable();
            $table->string('color', 7)->default('#8b5cf6');
            $table->integer('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // ─── Temas / Canciones ───
        Schema::create('temas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('genero_id')->constrained('generos')->onDelete('cascade');
            $table->string('nombre', 200);
            $table->string('slug', 200);
            $table->text('descripcion')->nullable();
            $table->string('compositor', 200)->nullable();
            $table->integer('duracion_segundos')->nullable();
            $table->integer('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['genero_id', 'slug']);
        });

        // ─── Partituras por Sección ───
        Schema::create('partituras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tema_id')->constrained('temas')->onDelete('cascade');
            $table->foreignId('seccion_id')->constrained('secciones');

            $table->string('titulo', 200)->nullable();
            $table->enum('tipo_archivo', ['pdf', 'imagen', 'audio'])->default('pdf');
            $table->string('archivo'); // Ruta del archivo
            $table->string('archivo_original'); // Nombre original
            $table->integer('tamaño_bytes')->nullable();
            $table->text('notas')->nullable();

            $table->foreignId('subido_por')->nullable()->constrained('miembros');
            $table->boolean('activo')->default(true);

            $table->timestamps();
        });

        // ─── Permisos especiales para subir partituras ───
        Schema::create('permisos_partituras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('miembro_id')->constrained('miembros')->onDelete('cascade');
            $table->foreignId('seccion_id')->nullable()->constrained('secciones')->onDelete('cascade');
            $table->boolean('puede_subir')->default(true);
            $table->boolean('puede_eliminar')->default(false);
            $table->foreignId('otorgado_por')->constrained('miembros');
            $table->timestamp('fecha_expiracion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permisos_partituras');
        Schema::dropIfExists('partituras');
        Schema::dropIfExists('temas');
        Schema::dropIfExists('generos');
    }
};
