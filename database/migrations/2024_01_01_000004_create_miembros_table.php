<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ═══════════════════════════════════════════════════════════
     * TABLA: MIEMBROS DE LA BANDA
     * ═══════════════════════════════════════════════════════════
     * Datos completos de cada músico
     */
    public function up(): void
    {
        Schema::create('miembros', function (Blueprint $table) {
            $table->id();

            // Relación con auth (1:1)
            $table->foreignId('user_id')->unique()->nullable()->constrained('users')->onDelete('set null');

            // ─── Datos Personales ───
            $table->string('nombres', 100);
            $table->string('apellidos', 100);

            // CI Separado (3FN)
            $table->string('ci_numero', 12);
            $table->string('ci_complemento', 5)->nullable();
            // Unique compuesto opcional, pero mejor manejarlo en lógica o con un índice
            $table->unique(['ci_numero', 'ci_complemento']);

            // Celular (Integer 8 dígitos) - Único
            $table->integer('celular')->unique();

            $table->date('fecha_nacimiento')->nullable();
            $table->string('foto')->nullable();

            // ─── Ubicación ───
            $table->text('direccion')->nullable();
            $table->decimal('latitud', 10, 8)->nullable(); // lat_casa
            $table->decimal('longitud', 11, 8)->nullable(); // lng_casa

            // ─── Contacto de Referencia ───
            $table->string('referencia_nombre', 100)->nullable();
            $table->string('referencia_celular', 20)->nullable();

            // ─── Relaciones ───
            $table->foreignId('seccion_id')->constrained('secciones');
            $table->foreignId('categoria_id')->constrained('categorias_salariales');

            // Rol eliminado, ahora es a través de User -> UserRole
            // ─── Estado ───
            $table->text('notas')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        // Tabla para tokens de API (Sanctum personalizado)
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('miembros');
    }
};
