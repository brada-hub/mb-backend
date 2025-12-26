<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ═══════════════════════════════════════════════════════════
     * TABLA: ROLES DEL SISTEMA
     * ═══════════════════════════════════════════════════════════
     * Super Admin, Director, Jefe de Sección, Miembro
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 50);
            $table->string('slug', 50)->unique();
            $table->text('descripcion')->nullable();
            $table->integer('nivel')->default(0); // Nivel jerárquico (mayor = más permisos)
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Tabla de permisos
        Schema::create('permisos', function (Blueprint $table) {
            $table->id();
            $table->string('modulo', 50); // eventos, miembros, partituras, etc.
            $table->string('accion', 50); // ver, crear, editar, eliminar
            $table->string('nombre', 100);
            $table->text('descripcion')->nullable();
            $table->timestamps();

            $table->unique(['modulo', 'accion']);
        });

        // Tabla pivote rol-permisos
        Schema::create('rol_permiso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rol_id')->constrained('roles')->onDelete('cascade');
            $table->foreignId('permiso_id')->constrained('permisos')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['rol_id', 'permiso_id']);
        });

        // Tabla pivote user_roles (Usuarios <-> Roles)
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('role_id')->constrained('roles')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['user_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('rol_permiso');
        Schema::dropIfExists('permisos');
        Schema::dropIfExists('roles');
    }
};
