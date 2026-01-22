<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MultiTenantTransitionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Crear la banda principal
        $bandaId = DB::table('bandas')->insertGetId([
            'nombre' => 'Monster Band',
            'slug' => 'monster-band',
            'color_primario' => '#6366f1',
            'color_secundario' => '#161b2c',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_banda');

        // 2. Asignar todos los registros existentes a esta banda
        $tables = [
            'users',
            'miembros',
            'eventos',
            'tipos_evento',
            'roles',
            'secciones',
            'instrumentos',
            'generos',
            'temas',
            'mixes',
            'notificaciones'
        ];

        foreach ($tables as $table) {
            DB::table($table)->whereNull('id_banda')->update(['id_banda' => $bandaId]);
        }
    }
}
