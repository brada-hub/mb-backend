<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Rol;
use App\Models\Seccion;
use App\Models\Categoria;
use App\Models\User;
use App\Models\Miembro;

class InitialCatalogSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Roles
        $adminRole = Rol::create(['rol' => 'ADMIN', 'descripcion' => 'ADMINISTRADOR DEL SISTEMA']);
        $directorRole = Rol::create(['rol' => 'DIRECTOR', 'descripcion' => 'DIRECTOR DE LA BANDA']);
        $jefeRole = Rol::create(['rol' => 'JEFE DE SECCION', 'descripcion' => 'ENCARGADO DE UNA SECCIÓN']);
        $miembroRole = Rol::create(['rol' => 'MIEMBRO', 'descripcion' => 'INTEGRANTE DE LA BANDA']);

        // 2. Secciones
        Seccion::create(['seccion' => 'PLATILLOS', 'descripcion' => 'SECCIÓN DE PERCUSIÓN']);
        Seccion::create(['seccion' => 'TAMBOR', 'descripcion' => 'SECCIÓN DE PERCUSIÓN']);
        Seccion::create(['seccion' => 'BOMBO', 'descripcion' => 'SECCIÓN DE PERCUSIÓN']);
        Seccion::create(['seccion' => 'TROMBON', 'descripcion' => 'SECCIÓN DE VIENTOS METAL']);
        Seccion::create(['seccion' => 'CLARINETE', 'descripcion' => 'SECCIÓN DE VIENTOS MADERA']);
        Seccion::create(['seccion' => 'BAJO', 'descripcion' => 'SECCIÓN DE CUERDAS GRAVES']);
        Seccion::create(['seccion' => 'TROMPETA', 'descripcion' => 'SECCIÓN DE VIENTOS METAL']);
        Seccion::create(['seccion' => 'HELICON', 'descripcion' => 'SECCIÓN DE CUERDAS GRAVES']);

        // 3. Categorías
        $catA = Categoria::create(['nombre_categoria' => 'A', 'descripcion' => 'NIVEL EXPERTO']);
        $catB = Categoria::create(['nombre_categoria' => 'B', 'descripcion' => 'NIVEL MEDIO']);

        // 4. Create initial Admin Member & User
        $adminMiembro = Miembro::create([
            'id_categoria' => $catA->id_categoria,
            'id_seccion' => 7, // TROMPETA
            'id_rol' => $adminRole->id_rol,
            'nombres' => 'ADMIN',
            'apellidos' => 'MONSTER',
            'ci' => '0000000',
            'celular' => '70000000',
            'direccion' => 'SEDE CENTRAL',
        ]);

        User::create([
            'user' => 'admin.monster@mb',
            'password' => Hash::make('monster2026'),
            'id_miembro' => $adminMiembro->id_miembro,
            'estado' => true,
            'password_changed' => true
        ]);

        // Director for web testing
        $directorMiembro = Miembro::create([
            'id_categoria' => $catA->id_categoria,
            'id_seccion' => 7, // TROMPETA
            'id_rol' => $directorRole->id_rol,
            'nombres' => 'DIRECTOR',
            'apellidos' => 'GENERAL',
            'ci' => '1111111',
            'celular' => '71111111',
            'direccion' => 'SEDE CENTRAL',
        ]);

        User::create([
            'user' => 'director.general@mb',
            'password' => Hash::make('director2026'),
            'id_miembro' => $directorMiembro->id_miembro,
            'estado' => true,
            'password_changed' => true
        ]);
    }
}
