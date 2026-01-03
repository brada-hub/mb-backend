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
        ];

        foreach ($permisos as $p) {
            Permiso::updateOrCreate(['permiso' => $p]);
        }

        // Link all to ADMIN
        $admin = Rol::where('rol', 'ADMIN')->first();
        if ($admin) {
            $admin->permisos()->sync(Permiso::all()->pluck('id_permiso'));
        }

        // Link all to DIRECTOR
        $director = Rol::where('rol', 'DIRECTOR')->first();
        if ($director) {
            $director->permisos()->sync(Permiso::all()->pluck('id_permiso'));
        }

        // Link to JEFE DE SECCION (Attendance + Dashboard)
        $jefe = Rol::where('rol', 'JEFE DE SECCION')->first();
        if ($jefe) {
            $jefe->permisos()->sync(Permiso::whereIn('permiso', [
                'VER_DASHBOARD',
                'GESTION_ASISTENCIA'
            ])->pluck('id_permiso'));
        }

        // Link only basic to MIEMBRO
        $miembro = Rol::where('rol', 'MIEMBRO')->first();
        if ($miembro) {
            $miembro->permisos()->sync(Permiso::whereIn('permiso', [
                'VER_DASHBOARD',
                'GESTION_BIBLIOTECA'
            ])->pluck('id_permiso'));
        }
    }
}
