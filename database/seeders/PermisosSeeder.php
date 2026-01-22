<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permiso;
use App\Models\Rol;

class PermisosSeeder extends Seeder
{
    public function run(): void
    {
        $permisos = [
            'GESTION_MIEMBROS',
            'GESTION_EVENTOS',
            'GESTION_ASISTENCIA',
            'GESTION_ROLES',
            'VER_DASHBOARD',
            'GESTION_FINANZAS',
            'GESTION_RECURSOS',
            'GESTION_SECCIONES',
            'GESTION_BIBLIOTECA',
            'ACCESO_WEB',
        ];

        foreach ($permisos as $p) {
            Permiso::updateOrCreate(['permiso' => $p]);
        }

        // Link all to ADMIN
        $super = Rol::where('rol', 'ADMIN')->first();
        if ($super) {
            $super->permisos()->sync(Permiso::all()->pluck('id_permiso'));
        }

        // Link all to DIRECTOR
        $director = Rol::where('rol', 'DIRECTOR')->first();
        if ($director) {
            $director->permisos()->sync(Permiso::all()->pluck('id_permiso'));
        }

        // Link to DELEGADO / JEFE (Attendance + Events + Dashboard)
        $delegado = Rol::where('rol', 'DELEGADO / JEFE')->first();
        if ($delegado) {
            $delegado->permisos()->sync(Permiso::whereIn('permiso', [
                'VER_DASHBOARD',
                'GESTION_ASISTENCIA',
                'GESTION_EVENTOS'
            ])->pluck('id_permiso'));
        }

        // Link basis to MÚSICO
        $miembro = Rol::where('rol', 'MÚSICO')->first();
        if ($miembro) {
            $miembro->permisos()->sync(Permiso::whereIn('permiso', [
                'VER_DASHBOARD',
                'GESTION_RECURSOS',
                'GESTION_BIBLIOTECA'
            ])->pluck('id_permiso'));
        }
    }
}
