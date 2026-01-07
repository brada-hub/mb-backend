<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Miembro;
use App\Models\Instrumento;
use App\Models\User;
use App\Models\Rol;
use App\Models\Categoria;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;

class MiembrosSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create('es_BO');

        $instrumentos = Instrumento::all();
        // Buscar el rol exacto "MIEMBRO"
        $rolMiembro = Rol::where('rol', 'MIEMBRO')->orWhere('rol', 'like', '%miembro%')->first()->id_rol ?? 4;
        $categoriaAup = Categoria::first()->id_categoria ?? 1;

        if ($instrumentos->isEmpty()) {
            $this->command->info('No hay instrumentos creados. Corre InstrumentosSeeder primero.');
            return;
        }

        $voces = [
            '1RA' => \App\Models\VozInstrumental::where('nombre_voz', '1RA VOZ')->first()->id_voz ?? null,
            '2DA' => \App\Models\VozInstrumental::where('nombre_voz', '2DA VOZ')->first()->id_voz ?? null,
            '3RA' => \App\Models\VozInstrumental::where('nombre_voz', '3RA VOZ')->first()->id_voz ?? null,
            '8VA' => \App\Models\VozInstrumental::where('nombre_voz', '8VA VOZ')->first()->id_voz ?? null,
            'GRAL' => \App\Models\VozInstrumental::where('nombre_voz', 'GENERAL')->first()->id_voz ?? null,
        ];

        foreach ($instrumentos as $instrumento) {
            $this->command->info("Creando miembros para: {$instrumento->instrumento}");
            $nombreInstr = strtoupper($instrumento->instrumento);

            // Determinar lógica de voces por instrumento
            // "CLARINETE S Y TROMBONES SOLO GENERAL HELICON GENERAL"
            // "TROMPETA Y BARITONOS BARIA ENTRE 1RA 2DA 3RA Y 8VA"
            // PERCUSION (PLATILLO, BOMBO, TAMBOR) -> GENERAL (Asumido por "TODO DEBE ESTAR EN MAYUSUCLA")

            for ($i = 0; $i < 3; $i++) {
                $nombre = strtoupper($faker->firstName);
                $apellido = strtoupper($faker->lastName);
                $ci = $faker->unique()->numberBetween(1000000, 9999999);
                $direccion = strtoupper($faker->address); // Todo mayúscula

                // Asignar Voz
                $vozId = $voces['GRAL']; // Default General

                if (in_array($nombreInstr, ['TROMPETA', 'BARÍTONO', 'BARITONO'])) {
                    // Variar entre 1ra, 2da, 3ra, 8va
                    $opciones = [$voces['1RA'], $voces['2DA'], $voces['3RA'], $voces['8VA']];
                    $vozId = $faker->randomElement($opciones);
                }

                // Nota: Clarinete, Trombón, Helicón, Percusión se quedan con GENERAL

                // Crear Miembro
                $miembro = Miembro::create([
                    'nombres' => $nombre,
                    'apellidos' => $apellido,
                    'ci' => $ci,
                    'celular' => '7' . $faker->numberBetween(1000000, 9999999),
                    'fecha' => $faker->date('Y-m-d', '2005-01-01'),
                    'direccion' => $direccion,
                    'id_seccion' => $instrumento->id_seccion,
                    'id_instrumento' => $instrumento->id_instrumento,
                    'id_voz' => $vozId,
                    'id_rol' => $rolMiembro,
                    'id_categoria' => $categoriaAup,
                ]);

                // Crear Usuario asociado (LOGIN: primernombre.apellido / PASS: 12345678)
                // User en minúscula para login, pero datos personales en mayúscula
                $username = strtolower(explode(' ', $nombre)[0] . '.' . explode(' ', $apellido)[0]);
                $username = preg_replace('/[^a-z0-9.]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $username));

                // Ensure unique username
                $baseUsername = $username;
                $count = 1;
                while(User::where('user', $username)->exists()) {
                    $username = $baseUsername . $count++;
                }

                User::create([
                    'user' => $username,
                    'password' => Hash::make('12345678'),
                    'id_miembro' => $miembro->id_miembro,
                    'estado' => true,
                    'password_changed' => true,
                ]);
            }
        }

        $this->command->info('¡Seeder completado! Miembros en MAYÚSCULAS y con asignación de voces correcta.');
    }
}
