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
            ['nombre' => 'Platillos', 'nombre_corto' => 'PLT', 'icono' => 'album', 'color' => '#f59e0b', 'es_viento' => false, 'orden' => 1],
            ['nombre' => 'Tambores / Tarolas', 'nombre_corto' => 'TAM', 'icono' => 'radio', 'color' => '#ef4444', 'es_viento' => false, 'orden' => 2],
            ['nombre' => 'Bombos', 'nombre_corto' => 'BOM', 'icono' => 'nightlife', 'color' => '#8b5cf6', 'es_viento' => false, 'orden' => 3],
            ['nombre' => 'Trompetas', 'nombre_corto' => 'TRP', 'icono' => 'campaign', 'color' => '#3b82f6', 'es_viento' => true, 'orden' => 4],
            ['nombre' => 'Trombones', 'nombre_corto' => 'TRB', 'icono' => 'music_note', 'color' => '#10b981', 'es_viento' => true, 'orden' => 5],
            ['nombre' => 'Clarinetes', 'nombre_corto' => 'CLR', 'icono' => 'piano', 'color' => '#ec4899', 'es_viento' => true, 'orden' => 6],
            ['nombre' => 'Bajos / Barítonos', 'nombre_corto' => 'BAR', 'icono' => 'graphic_eq', 'color' => '#6366f1', 'es_viento' => true, 'orden' => 7],
            ['nombre' => 'Helicones / Tubas', 'nombre_corto' => 'HEL', 'icono' => 'surround_sound', 'color' => '#14b8a6', 'es_viento' => true, 'orden' => 8],
        ];

        foreach ($secciones as $seccion) {
            Seccion::create($seccion);
        }
    }

    private function crearCategoriasSalariales(): void
    {
        $categorias = [
            ['codigo' => 'A', 'nombre' => 'Categoría A', 'descripcion' => 'Músicos con mayor experiencia y responsabilidad', 'monto_base' => 200, 'orden' => 1],
            ['codigo' => 'B', 'nombre' => 'Categoría B', 'descripcion' => 'Músicos con experiencia intermedia', 'monto_base' => 150, 'orden' => 2],
            ['codigo' => 'C', 'nombre' => 'Categoría C', 'descripcion' => 'Músicos nuevos o en formación', 'monto_base' => 100, 'orden' => 3],
        ];

        foreach ($categorias as $categoria) {
            CategoriaSalarial::create($categoria);
        }
    }

    private function crearRolesYPermisos(): void
    {
        // Crear roles
        $roles = [
            ['nombre' => 'Super Administrador', 'slug' => 'super_admin', 'descripcion' => 'Control total del sistema', 'nivel' => 100],
            ['nombre' => 'Director', 'slug' => 'director', 'descripcion' => 'Director de la banda', 'nivel' => 80],
            ['nombre' => 'Jefe de Sección', 'slug' => 'jefe_seccion', 'descripcion' => 'Jefe de una sección musical', 'nivel' => 50],
            ['nombre' => 'Miembro', 'slug' => 'miembro', 'descripcion' => 'Músico de la banda', 'nivel' => 10],
        ];

        foreach ($roles as $rol) {
            Rol::create($rol);
        }

        // Crear permisos
        $modulos = [
            'miembros' => ['ver', 'crear', 'editar', 'eliminar'],
            'eventos' => ['ver', 'crear', 'editar', 'eliminar'],
            'asistencias' => ['ver', 'registrar', 'editar'],
            'partituras' => ['ver', 'subir', 'eliminar'],
            'pagos' => ['ver', 'crear', 'editar', 'anular'],
            'reportes' => ['ver', 'exportar'],
            'configuracion' => ['ver', 'editar'],
        ];

        foreach ($modulos as $modulo => $acciones) {
            foreach ($acciones as $accion) {
                Permiso::create([
                    'modulo' => $modulo,
                    'accion' => $accion,
                    'nombre' => ucfirst($accion) . ' ' . $modulo,
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
            ['nombre' => 'Morenada', 'icono' => 'music_note', 'color' => '#dc2626', 'orden' => 1],
            ['nombre' => 'Diablada', 'icono' => 'whatshot', 'color' => '#f97316', 'orden' => 2],
            ['nombre' => 'Caporales', 'icono' => 'celebration', 'color' => '#eab308', 'orden' => 3],
            ['nombre' => 'Tinkus', 'icono' => 'accessibility_new', 'color' => '#22c55e', 'orden' => 4],
            ['nombre' => 'Salay', 'icono' => 'nightlife', 'color' => '#3b82f6', 'orden' => 5],
            ['nombre' => 'Tobas', 'icono' => 'forest', 'color' => '#8b5cf6', 'orden' => 6],
            ['nombre' => 'Llamerada', 'icono' => 'pets', 'color' => '#ec4899', 'orden' => 7],
            ['nombre' => 'Saya', 'icono' => 'groups', 'color' => '#14b8a6', 'orden' => 8],
            ['nombre' => 'Waca Waca', 'icono' => 'agriculture', 'color' => '#f59e0b', 'orden' => 9],
            ['nombre' => 'Pujllay', 'icono' => 'mood', 'color' => '#6366f1', 'orden' => 10],
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
            'nombres' => 'Administrador',
            'apellidos' => 'Sistema',
            'ci_numero' => '0000001',
            'ci_complemento' => null,
            'celular' => 70000000,
            'seccion_id' => $seccion->id,
            'categoria_id' => $categoria->id,
            // 'rol_id' ya no existe en miembro
        ]);
    }
}
