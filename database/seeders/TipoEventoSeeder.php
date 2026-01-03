<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TipoEventoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tipos = [
            ['evento' => 'ENSAYO'],
            ['evento' => 'PRESENTACION'],
            ['evento' => 'REUNION'],
            ['evento' => 'FIESTA'],
            ['evento' => 'VIAJE'],
            ['evento' => 'OTRO'],
        ];

        DB::table('tipos_evento')->insert($tipos);
    }
}
