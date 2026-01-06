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

        foreach ($instrumentos as $instrumento) {
            $this->command->info("Creando miembros para: {$instrumento->instrumento}");

            for ($i = 0; $i < 3; $i++) {
                $nombre = $faker->firstName;
                $apellido = $faker->lastName;
                $ci = $faker->unique()->numberBetween(1000000, 9999999);

                // Crear Miembro
                $miembro = Miembro::create([
                    'nombres' => $nombre,
                    'apellidos' => $apellido,
                    'ci' => $ci,
                    'celular' => '7' . $faker->numberBetween(1000000, 9999999),
                    'fecha' => $faker->date('Y-m-d', '2005-01-01'), // Joven
                    'direccion' => $faker->address,
                    'id_seccion' => $instrumento->id_seccion,
                    'id_instrumento' => $instrumento->id_instrumento,
                    'id_rol' => $rolMiembro,
                    'id_categoria' => $categoriaAup,
                ]);

                // Crear Usuario asociado (LOGIN: primernombre.apellido / PASS: 12345678)
                $username = strtolower(explode(' ', $nombre)[0] . '.' . explode(' ', $apellido)[0]);
                // Sanitize username
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
                    'password_changed' => true, // Ya cambiada por defecto para facilitar pruebas
                ]);
            }
        }

        $this->command->info('¡Seeder completado! Se crearon 3 miembros por cada instrumento.');
    }
}
