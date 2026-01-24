<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Models\Archivo;
use App\Models\Recurso;
use App\Models\Tema;
use App\Models\Instrumento;

class ImportFilesCommand extends Command
{
    // El nombre del comando que ejecutarás
    protected $signature = 'library:import';
    protected $description = 'Enlaza automáticamente los archivos huerfanos de la carpeta recursos a sus temas';

    public function handle()
    {
        $this->info('Iniciando escaneo de la carpeta recursos...');

        // 1. Obtenemos todos los archivos de la carpeta física
        $files = Storage::disk('public')->files('recursos');

        // 2. Buscamos un instrumento por defecto (Ej: Trompeta) para asignar las partituras
        // Si quieres cambiarlos luego, puedes editarlos en el panel
        $instrumentoDefault = Instrumento::where('instrumento', 'LIKE', '%TROMPETA%')->first()
                             ?? Instrumento::first();

        if (!$instrumentoDefault) {
            $this->error('¡Error! No encontré ningún instrumento en la base de datos para asignar las partituras.');
            return;
        }

        $count = 0;
        $skipped = 0;

        foreach ($files as $filePath) {
            $filename = basename($filePath);

            // Ignoramos archivos ocultos o extraños
            if (str_starts_with($filename, '.')) continue;

            // Deducimos el nombre del tema. Ej: "AMAMÉ.png" -> Busca el tema "AMAMÉ"
            $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);

            // Limpiamos un poco el nombre (quitamos guiones bajos si hay)
            $searchTerm = str_replace('_', ' ', $nameWithoutExt);

            // Buscamos un tema que contenga ese nombre
            $tema = Tema::where('nombre_tema', 'LIKE', '%' . $searchTerm . '%')->first();

            if ($tema) {
                // Verificamos si este archivo ya estaba enlazado para no duplicar
                $exists = Archivo::where('nombre_original', $filename)->exists();

                if (!$exists) {
                    // Creamos el Recurso (El "padre" de la partitura)
                    $recurso = Recurso::create([
                        'id_tema' => $tema->id_tema,
                        'id_instrumento' => $instrumentoDefault->id_instrumento,
                        'id_voz' => null // Voz general
                    ]);

                    // Creamos el Archivo (El enlace al archivo físico)
                    Archivo::create([
                        'id_recurso' => $recurso->id_recurso,
                        'url_archivo' => 'storage/recursos/' . $filename,
                        'tipo' => str_ends_with(strtolower($filename), 'pdf') ? 'pdf' : 'imagen',
                        'nombre_original' => $filename,
                        'orden' => 1
                    ]);

                    $this->info("✅ Enlazado: $filename -> Tema: {$tema->nombre_tema}");
                    $count++;
                } else {
                    $skipped++;
                }
            } else {
                $this->warn("⚠️ No encontré tema para: $filename (Tal vez el seeder no lo creó o se llama distinto)");
            }
        }

        $this->info("------------------------------------------------");
        $this->info("¡Proceso terminado pes :vvvv!");
        $this->info("Archivos nuevos enlazados: $count");
        $this->info("Archivos ya existentes saltados: $skipped");
    }
}
