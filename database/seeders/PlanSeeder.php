<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\Plan::updateOrCreate(['nombre' => 'BASIC'], [
            'label' => 'Básico',
            'max_miembros' => 15,
            'storage_mb' => 100,
            'can_upload_audio' => false,
            'can_upload_video' => false,
            'gps_attendance' => false,
            'custom_branding' => false,
            'precio_base' => 0,
            'features' => ['Partituras PDF', 'Imágenes (JPG/PNG)', 'Dashboard Personal']
        ]);

        \App\Models\Plan::updateOrCreate(['nombre' => 'PREMIUM'], [
            'label' => 'Premium',
            'max_miembros' => 50,
            'storage_mb' => 500,
            'can_upload_audio' => true,
            'can_upload_video' => false,
            'gps_attendance' => true,
            'custom_branding' => false,
            'precio_base' => 20,
            'features' => ['Todo lo del Básico', 'Archivos de Audio (MP3)', 'Control de Asistencia GPS', 'Gestión de Secciones']
        ]);

        \App\Models\Plan::updateOrCreate(['nombre' => 'PRO'], [
            'label' => 'Profesional (Monster)',
            'max_miembros' => 1000,
            'storage_mb' => 5120,
            'can_upload_audio' => true,
            'can_upload_video' => true,
            'gps_attendance' => true,
            'custom_branding' => true,
            'precio_base' => 50,
            'features' => ['Todo lo Premium', 'Biblioteca de Video', 'Control de Remuneraciones', 'Soporte 24/7', 'Personalización de Marca']
        ]);
    }
}
