<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PlanSeeder::class,
            SuperAdminSeeder::class,
            InitialCatalogSeeder::class,
            PermisosSeeder::class,
            MusicLibrarySeeder::class,
            TipoEventoSeeder::class,
            MiembrosSeeder::class,
            EventosSeeder::class,
            RealDemoDataSeeder::class,
        ]);
    }
}
