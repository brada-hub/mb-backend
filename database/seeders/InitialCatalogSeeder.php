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
use App\Models\VozInstrumental;

class InitialCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $proPlan = \App\Models\Plan::where('nombre', 'PRO')->first();

        // 0. Crear Banda Principal (Monster Band)
        $banda = \App\Models\Banda::create([
            'nombre' => 'Monster Band',
            'slug' => 'monster-band',
            'logo' => null,
            'color_primario' => '#6366f1',
            'color_secundario' => '#161b2c',
            'estado' => true,
            'plan' => 'PRO',
            'id_plan' => $proPlan?->id_plan,
            'max_miembros' => 100
        ]);

        // 1. Roles (Asignados a la banda)
        $superRole = Rol::create(['rol' => 'ADMIN', 'descripcion' => 'ADMINISTRADOR DE LA PLATAFORMA (GLOBAL)', 'id_banda' => $banda->id_banda, 'es_protegido' => true]);

        // Roles de Fábrica
        $directorRole = Rol::create([
            'rol' => 'DIRECTOR',
            'descripcion' => 'CONTROL TOTAL DE LA BANDA (GESTIÓN, FINANZAS Y CÁTALOGOS)',
            'id_banda' => $banda->id_banda,
            'es_protegido' => true
        ]);
        $delegadoRole = Rol::create([
            'rol' => 'JEFE DE SECCIÓN',
            'descripcion' => 'CONTROL DE MIEMBROS DE SU INSTRUMENTO',
            'id_banda' => $banda->id_banda,
            'es_protegido' => true
        ]);
        $miembroRole = Rol::create([
            'rol' => 'MÚSICO',
            'descripcion' => 'VISTA RESTRINGIDA A SU PERFIL Y AGENDA',
            'id_banda' => $banda->id_banda,
            'es_protegido' => true
        ]);

        // 2. Secciones e Instrumentos
        // Estructura: 'NOMBRE_SECCION' => ['Instr1', 'Instr2', ...]
        $catalog = [
            'PERCUSIÓN' => ['PLATILLO', 'TAMBOR', 'TIMBAL', 'BOMBO'],
            'VIENTOS' => ['TROMPETA', 'TROMBÓN', 'BARÍTONO', 'HELICÓN', 'CLARINETE']
        ];

        // Guardamos referencias para asignar al admin después
        $trompetaId = null;

        foreach ($catalog as $secName => $instruments) {
            $seccion = Seccion::create([
                'seccion' => $secName,
                'descripcion' => 'SECCIÓN DE ' . $secName,
                'estado' => true, // Default active
                'id_banda' => $banda->id_banda
            ]);

            foreach ($instruments as $instName) {
                $inst = Instrumento::create([
                    'instrumento' => $instName,
                    'id_seccion' => $seccion->id_seccion,
                    'id_banda' => $banda->id_banda
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


        // 3. Categorías (Globales o por banda? Asumimos globales por ahora, pero editamos si necesario)
        // Schema categorias no tiene id_banda según lo visto antes, son catálogos globales.
        $catA = Categoria::create(['nombre_categoria' => 'A', 'descripcion' => 'NIVEL EXPERTO']);
        $catB = Categoria::create(['nombre_categoria' => 'B', 'descripcion' => 'NIVEL MEDIO']);
        $catC = Categoria::create(['nombre_categoria' => 'C', 'descripcion' => 'NIVEL BAJO']);
        $catN = Categoria::create(['nombre_categoria' => 'N', 'descripcion' => 'NIVEL INICIAL']);

        // 3.5 Voces Instrumentales (Base)
        $v1 = VozInstrumental::updateOrCreate(['nombre_voz' => '1RA VOZ']);
        $v2 = VozInstrumental::updateOrCreate(['nombre_voz' => '2DA VOZ']);
        $v3 = VozInstrumental::updateOrCreate(['nombre_voz' => '3RA VOZ']);
        VozInstrumental::updateOrCreate(['nombre_voz' => '8VA VOZ']);
        VozInstrumental::updateOrCreate(['nombre_voz' => 'GENERAL']);

        // 4. CREACIÓN DE USUARIOS DE PRUEBA (Credenciales = Usuario)

        // A. SUPER ADMIN (Dueño del SaaS - NO tiene miembro, NO pertenece a ninguna banda)
        User::updateOrCreate(
            ['user' => 'admin'],
            [
                'password' => Hash::make('admin'),
                'id_miembro' => null, // SuperAdmin NO tiene perfil de miembro
                'estado' => true,
                'password_changed' => true,
                'id_banda' => null, // NO pertenece a ninguna banda
                'is_super_admin' => true
            ]
        );

        // B. DIRECTOR (Dueño de su Banda)
        $directorMiembro = Miembro::firstOrCreate(
            ['ci' => '1111111'],
            [
                'id_categoria' => $catA->id_categoria,
                'id_seccion' => $trompetaSeccionId,
                'id_instrumento' => $trompetaId,
                'id_voz' => $v1->id_voz,
                'id_rol' => $directorRole->id_rol,
                'nombres' => 'DIRECTOR',
                'apellidos' => 'BANDA',
                'celular' => '71111111',
                'direccion' => 'CENTRO',
                'id_banda' => $banda->id_banda
            ]
        );

        User::updateOrCreate(
            ['user' => 'director'],
            [
                'password' => Hash::make('director'),
                'id_miembro' => $directorMiembro->id_miembro,
                'estado' => true,
                'password_changed' => true,
                'id_banda' => $banda->id_banda,
                'is_super_admin' => false
            ]
        );

        // C. JEFE DE SECCIÓN (Visualiza su instrumento)
        $jefeMiembro = Miembro::firstOrCreate(
            ['ci' => '2222222'],
            [
                'id_categoria' => $catB->id_categoria,
                'id_seccion' => $trompetaSeccionId,
                'id_instrumento' => $trompetaId,
                'id_voz' => $v1->id_voz,
                'id_rol' => $delegadoRole->id_rol,
                'nombres' => 'JEFE',
                'apellidos' => 'SECCIÓN',
                'celular' => '72222222',
                'direccion' => 'ZONA SUR',
                'id_banda' => $banda->id_banda
            ]
        );

        User::updateOrCreate(
            ['user' => 'jefeseccion'],
            [
                'password' => Hash::make('jefeseccion'),
                'id_miembro' => $jefeMiembro->id_miembro,
                'estado' => true,
                'password_changed' => true,
                'id_banda' => $banda->id_banda,
                'is_super_admin' => false
            ]
        );

        // D. MIEMBRO (Solo visualiza su perfil/asistencia)
        $miembroUserRecord = Miembro::firstOrCreate(
            ['ci' => '3333333'],
            [
                'id_categoria' => $catC->id_categoria,
                'id_seccion' => $trompetaSeccionId,
                'id_instrumento' => $trompetaId,
                'id_voz' => $v3->id_voz,
                'id_rol' => $miembroRole->id_rol,
                'nombres' => 'MÚSICO',
                'apellidos' => 'BASE',
                'celular' => '73333333',
                'direccion' => 'ZONA NORTE',
                'id_banda' => $banda->id_banda
            ]
        );

        User::updateOrCreate(
            ['user' => 'miembro'],
            [
                'password' => Hash::make('miembro'),
                'id_miembro' => $miembroUserRecord->id_miembro,
                'estado' => true,
                'password_changed' => true,
                'id_banda' => $banda->id_banda,
                'is_super_admin' => false
            ]
        );
    }
}
