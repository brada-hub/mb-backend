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
            ['evento' => 'CONTRATO'],
            ['evento' => 'BANDIN'],
            ['evento' => 'VELADA'],
            ['evento' => 'PRESENTACION'],
            ['evento' => 'REUNION'],
            ['evento' => 'OTRO'],
        ];

        DB::table('tipos_evento')->insert($tipos);
    }
}
