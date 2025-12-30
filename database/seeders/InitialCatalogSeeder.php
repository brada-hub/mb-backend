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
        $adminRole = Rol::create(['rol' => 'Admin', 'descripcion' => 'Super usuario']);
        $directorRole = Rol::create(['rol' => 'Director', 'descripcion' => 'Director de la banda']);
        $musicianRole = Rol::create(['rol' => 'Músico', 'descripcion' => 'Integrante de la banda']);

        // 2. Secciones
        Seccion::create(['seccion' => 'Trompetas', 'descripcion' => 'Sección de vientos metal']);
        Seccion::create(['seccion' => 'Percusión', 'descripcion' => 'Batería y accesorios']);
        Seccion::create(['seccion' => 'Bajos', 'descripcion' => 'Sección de cuerdas graves']);

        // 3. Categorías
        $catA = Categoria::create(['nombre_categoria' => 'A', 'descripcion' => 'Nivel Experto']);
        $catB = Categoria::create(['nombre_categoria' => 'B', 'descripcion' => 'Nivel Medio']);

        // 4. Create initial Admin Member & User
        $adminMiembro = Miembro::create([
            'id_categoria' => $catA->id_categoria,
            'id_seccion' => 1, // Trompetas
            'id_rol' => $adminRole->id_rol,
            'nombres' => 'Admin',
            'apellidos' => 'Monster',
            'ci' => '0000000',
            'celular' => 70000000,
            'direccion' => 'Sede Central',
        ]);

        User::create([
            'user' => 'admin',
            'password' => Hash::make('monster2025'),
            'id_miembro' => $adminMiembro->id_miembro,
            'estado' => true
        ]);

        // Director for web testing
        $directorMiembro = Miembro::create([
            'id_categoria' => $catA->id_categoria,
            'id_seccion' => 1,
            'id_rol' => $directorRole->id_rol,
            'nombres' => 'Director',
            'apellidos' => 'General',
            'ci' => '1111111',
            'celular' => 71111111,
        ]);

        User::create([
            'user' => 'director',
            'password' => Hash::make('director2025'),
            'id_miembro' => $directorMiembro->id_miembro,
            'estado' => true
        ]);
    }
}
