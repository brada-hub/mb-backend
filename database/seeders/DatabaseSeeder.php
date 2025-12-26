<?php

namespace Database\Seeders;

use App\Models\Seccion;
use App\Models\CategoriaSalarial;
use App\Models\Rol;
use App\Models\Permiso;
use App\Models\Miembro;
use App\Models\Tarifa;
use App\Models\Genero;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->crearSecciones();
        $this->crearCategoriasSalariales();
        $this->crearRolesYPermisos();
        $this->crearTarifas();
        $this->crearGenerosMusicales();
        $this->crearSuperAdmin();
    }

    private function crearSecciones(): void
    {
        $secciones = [
            ['nombre' => 'PLATILLOS', 'nombre_corto' => 'PLT', 'icono' => 'album', 'color' => '#f59e0b', 'es_viento' => false, 'orden' => 1],
            ['nombre' => 'TAMBORES / TAROLAS', 'nombre_corto' => 'TAM', 'icono' => 'radio', 'color' => '#ef4444', 'es_viento' => false, 'orden' => 2],
            ['nombre' => 'BOMBOS', 'nombre_corto' => 'BOM', 'icono' => 'nightlife', 'color' => '#8b5cf6', 'es_viento' => false, 'orden' => 3],
            ['nombre' => 'TROMPETAS', 'nombre_corto' => 'TRP', 'icono' => 'campaign', 'color' => '#3b82f6', 'es_viento' => true, 'orden' => 4],
            ['nombre' => 'TROMBONES', 'nombre_corto' => 'TRB', 'icono' => 'music_note', 'color' => '#10b981', 'es_viento' => true, 'orden' => 5],
            ['nombre' => 'CLARINETES', 'nombre_corto' => 'CLR', 'icono' => 'piano', 'color' => '#ec4899', 'es_viento' => true, 'orden' => 6],
            ['nombre' => 'BAJOS / BARÍTONOS', 'nombre_corto' => 'BAR', 'icono' => 'graphic_eq', 'color' => '#6366f1', 'es_viento' => true, 'orden' => 7],
            ['nombre' => 'HELICONES / TUBAS', 'nombre_corto' => 'HEL', 'icono' => 'surround_sound', 'color' => '#14b8a6', 'es_viento' => true, 'orden' => 8],
        ];

        foreach ($secciones as $seccion) {
            Seccion::create($seccion);
        }
    }

    private function crearCategoriasSalariales(): void
    {
        $categorias = [
            ['codigo' => 'A', 'nombre' => 'CATEGORÍA A', 'descripcion' => 'MÚSICOS CON MAYOR EXPERIENCIA Y RESPONSABILIDAD', 'monto_base' => 200, 'orden' => 1],
            ['codigo' => 'B', 'nombre' => 'CATEGORÍA B', 'descripcion' => 'MÚSICOS CON EXPERIENCIA INTERMEDIA', 'monto_base' => 150, 'orden' => 2],
            ['codigo' => 'C', 'nombre' => 'CATEGORÍA C', 'descripcion' => 'MÚSICOS NUEVOS O EN FORMACIÓN', 'monto_base' => 100, 'orden' => 3],
        ];

        foreach ($categorias as $categoria) {
            CategoriaSalarial::create($categoria);
        }
    }

    private function crearRolesYPermisos(): void
    {
        // Crear roles
        $roles = [
            ['nombre' => 'SUPER ADMINISTRADOR', 'slug' => 'super_admin', 'descripcion' => 'CONTROL TOTAL DEL SISTEMA', 'nivel' => 100],
            ['nombre' => 'DIRECTOR', 'slug' => 'director', 'descripcion' => 'DIRECTOR DE LA BANDA', 'nivel' => 80],
            ['nombre' => 'JEFE DE SECCIÓN', 'slug' => 'jefe_seccion', 'descripcion' => 'JEFE DE UNA SECCIÓN MUSICAL', 'nivel' => 50],
            ['nombre' => 'MIEMBRO', 'slug' => 'miembro', 'descripcion' => 'MÚSICO DE LA BANDA', 'nivel' => 10],
        ];

        foreach ($roles as $rol) {
            Rol::create($rol);
        }

        // Crear permisos
        $modulos = [
            'MIEMBROS' => ['VER', 'CREAR', 'EDITAR', 'ELIMINAR'],
            'EVENTOS' => ['VER', 'CREAR', 'EDITAR', 'ELIMINAR'],
            'ASISTENCIAS' => ['VER', 'REGISTRAR', 'EDITAR'],
            'PARTITURAS' => ['VER', 'SUBIR', 'ELIMINAR'],
            'PAGOS' => ['VER', 'CREAR', 'EDITAR', 'ANULAR'],
            'REPORTES' => ['VER', 'EXPORTAR'],
            'CONFIGURACION' => ['VER', 'EDITAR'],
        ];

        foreach ($modulos as $modulo => $acciones) {
            foreach ($acciones as $accion) {
                // Generar slugs en minúsculas para uso interno, pero nombres visibles en mayúsculas
                $slugModulo = strtolower($modulo);
                $slugAccion = strtolower($accion);

                Permiso::create([
                    'modulo' => $slugModulo,
                    'accion' => $slugAccion,
                    'nombre' => $accion . ' ' . $modulo,
                ]);
            }
        }

        // Asignar todos los permisos al Super Admin y Director
        $superAdmin = Rol::where('slug', 'super_admin')->first();
        $director = Rol::where('slug', 'director')->first();
        $todosPermisos = Permiso::pluck('id');

        $superAdmin->permisos()->attach($todosPermisos);
        $director->permisos()->attach($todosPermisos);

        // Permisos para Jefe de Sección
        $jefeSeccion = Rol::where('slug', 'jefe_seccion')->first();
        $permisosJefe = Permiso::whereIn('modulo', ['eventos', 'asistencias', 'partituras'])
            ->orWhere(function ($q) {
                $q->where('modulo', 'miembros')->where('accion', 'ver');
            })
            ->pluck('id');
        $jefeSeccion->permisos()->attach($permisosJefe);

        // Permisos para Miembro
        $miembro = Rol::where('slug', 'miembro')->first();
        $permisosMiembro = Permiso::where('accion', 'ver')
            ->whereIn('modulo', ['eventos', 'partituras', 'pagos'])
            ->pluck('id');
        $miembro->permisos()->attach($permisosMiembro);
    }

    private function crearTarifas(): void
    {
        $secciones = Seccion::all();
        $categorias = CategoriaSalarial::all();

        // Tarifas base por categoría
        $tarifasBase = [
            'A' => ['ensayo' => 50, 'contrato' => 200],
            'B' => ['ensayo' => 40, 'contrato' => 150],
            'C' => ['ensayo' => 30, 'contrato' => 100],
        ];

        foreach ($secciones as $seccion) {
            foreach ($categorias as $categoria) {
                Tarifa::create([
                    'seccion_id' => $seccion->id,
                    'categoria_id' => $categoria->id,
                    'monto_ensayo' => $tarifasBase[$categoria->codigo]['ensayo'],
                    'monto_contrato' => $tarifasBase[$categoria->codigo]['contrato'],
                ]);
            }
        }
    }

    private function crearGenerosMusicales(): void
    {
        $generos = [
            ['nombre' => 'MORENADA', 'icono' => 'music_note', 'color' => '#dc2626', 'orden' => 1],
            ['nombre' => 'DIABLADA', 'icono' => 'whatshot', 'color' => '#f97316', 'orden' => 2],
            ['nombre' => 'CAPORALES', 'icono' => 'celebration', 'color' => '#eab308', 'orden' => 3],
            ['nombre' => 'TINKUS', 'icono' => 'accessibility_new', 'color' => '#22c55e', 'orden' => 4],
            ['nombre' => 'SALAY', 'icono' => 'nightlife', 'color' => '#3b82f6', 'orden' => 5],
            ['nombre' => 'TOBAS', 'icono' => 'forest', 'color' => '#8b5cf6', 'orden' => 6],
            ['nombre' => 'LLAMERADA', 'icono' => 'pets', 'color' => '#ec4899', 'orden' => 7],
            ['nombre' => 'SAYA', 'icono' => 'groups', 'color' => '#14b8a6', 'orden' => 8],
            ['nombre' => 'WACA WACA', 'icono' => 'agriculture', 'color' => '#f59e0b', 'orden' => 9],
            ['nombre' => 'PUJLLAY', 'icono' => 'mood', 'color' => '#6366f1', 'orden' => 10],
        ];

        foreach ($generos as $genero) {
            Genero::create($genero);
        }
    }

    private function crearSuperAdmin(): void
    {
        $rolSuperAdmin = Rol::where('slug', 'super_admin')->first();
        $seccion = Seccion::first();
        $categoria = CategoriaSalarial::where('codigo', 'A')->first();

        // 1. Crear Usuario
        $user = \App\Models\User::create([
            'username' => 'admin',
            'password' => Hash::make('Monster2024!'), // Contraseña por defecto
            'activo' => true,
            'multi_login' => true,
            'cambio_password_requerido' => false,
        ]);

        // 2. Asignar Rol
        $user->roles()->attach($rolSuperAdmin->id);

        // 3. Crear Perfil de Miembro
        Miembro::create([
            'user_id' => $user->id,
            'nombres' => 'ADMINISTRADOR',
            'apellidos' => 'SISTEMA',
            'ci_numero' => '0000001',
            'ci_complemento' => null,
            'celular' => 70000000,
            'seccion_id' => $seccion->id,
            'categoria_id' => $categoria->id,
            // 'rol_id' ya no existe en miembro
        ]);
    }
}
