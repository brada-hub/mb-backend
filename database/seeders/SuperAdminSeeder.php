<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear Super Admin sin banda (acceso global)
        User::updateOrCreate(
            ['user' => 'superadmin'],
            [
                'password' => Hash::make('superadmin'),
                'estado' => true,
                'is_super_admin' => true,
                'id_miembro' => null, // No asociado a ningún miembro de banda
                'id_banda' => null,   // Acceso global, no limitado a una banda
                'password_changed' => true
            ]
        );

        $this->command->info('✅ Super Admin creado:');
        $this->command->info('   Usuario: superadmin');
        $this->command->info('   Contraseña: superadmin');
        $this->command->warn('   ⚠️  ¡Cambia esta contraseña en producción!');
    }
}
