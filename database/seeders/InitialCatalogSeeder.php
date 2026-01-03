<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Rol;
use App\Models\Seccion;
use App\Models\Instrumento;
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

        // 2. Secciones e Instrumentos
        // Estructura: 'NOMBRE_SECCION' => ['Instr1', 'Instr2', ...]
        $catalog = [
            'PERCUSIÓN' => ['PLATILLO', 'TAMBOR', 'BOMBO'],
            'METALES' => ['TROMPETA', 'TROMBÓN', 'BARÍTONO', 'HELICÓN'],
            'MADERAS' => ['CLARINETE']
        ];

        // Guardamos referencias para asignar al admin después
        $trompetaId = null;

        foreach ($catalog as $secName => $instruments) {
            $seccion = Seccion::create([
                'seccion' => $secName,
                'descripcion' => 'SECCIÓN DE ' . $secName,
                'estado' => true // Default active
            ]);

            foreach ($instruments as $instName) {
                $inst = Instrumento::create([
                    'instrumento' => $instName,
                    'id_seccion' => $seccion->id_seccion
                ]);

                if ($instName === 'TROMPETA') {
                    $trompetaId = $inst->id_instrumento;
                }
            }
        }

        // Fallback por si no se creó trompeta
        if (!$trompetaId) {
            $firstInst = Instrumento::first();
            $trompetaId = $firstInst ? $firstInst->id_instrumento : null;
        }

        $trompetaInst = Instrumento::find($trompetaId);
        $trompetaSeccionId = $trompetaInst ? $trompetaInst->id_seccion : 1;


        // 3. Categorías
        $catA = Categoria::create(['nombre_categoria' => 'A', 'descripcion' => 'NIVEL EXPERTO']);
        $catB = Categoria::create(['nombre_categoria' => 'B', 'descripcion' => 'NIVEL MEDIO']);

        // 4. Create initial Admin Member & User
        $adminMiembro = Miembro::create([
            'id_categoria' => $catA->id_categoria,
            'id_seccion' => $trompetaSeccionId,
            'id_instrumento' => $trompetaId,
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
            'id_seccion' => $trompetaSeccionId,
            'id_instrumento' => $trompetaId,
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
