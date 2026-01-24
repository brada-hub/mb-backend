<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Models\Archivo;
use App\Models\Recurso;
use App\Models\Tema;
use App\Models\Instrumento;
use App\Models\VozInstrumental;

class ImportFilesCommand extends Command
{
    protected $signature = 'library:import';
    protected $description = 'Enlaza las partituras físicas a Trompeta - 1ra Voz';

    public function handle()
    {
        $this->info('Iniciando escaneo para TROMPETA - 1RA VOZ...');

        // 1. Buscamos el instrumento TROMPETA
        $instrumento = Instrumento::where('instrumento', 'LIKE', '%TROMPETA%')->first();

        // 2. Buscamos la 1RA VOZ
        $voz = VozInstrumental::where('nombre_voz', 'LIKE', '%1RA%')->first();

        if (!$instrumento || !$voz) {
            $this->error('¡Error! No encontré "TROMPETA" o "1RA VOZ" en tus catálogos.');
            return;
        }

        $this->info("Usando Instrumento: {$instrumento->instrumento} | Voz: {$voz->nombre_voz}");

        // 3. Escaneamos la carpeta
        $files = Storage::disk('public')->files('recursos');
        $count = 0;

        foreach ($files as $filePath) {
            $filename = basename($filePath);
            if (str_starts_with($filename, '.')) continue;

            // Extraemos el nombre del tema (ej: "AMAMÉ.png" -> "AMAMÉ")
            $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
            $searchTerm = str_replace('_', ' ', $nameWithoutExt);

            // Buscamos el tema en la DB
            $tema = Tema::where('nombre_tema', 'LIKE', '%' . $searchTerm . '%')->first();

            if ($tema) {
                // Borramos cualquier enlace previo de este tema para este instrumento si existe (para limpiar errores)
                $oldRecursos = Recurso::where('id_tema', $tema->id_tema)
                                     ->where('id_instrumento', $instrumento->id_instrumento)
                                     ->get();
                foreach($oldRecursos as $or) $or->delete();

                // Creamos el Recurso correcto
                $recurso = Recurso::create([
                    'id_tema' => $tema->id_tema,
                    'id_instrumento' => $instrumento->id_instrumento,
                    'id_voz' => $voz->id_voz
                ]);

                // Enlazamos el archivo físico
                Archivo::create([
                    'id_recurso' => $recurso->id_recurso,
                    'url_archivo' => 'storage/recursos/' . $filename,
                    'tipo' => str_ends_with(strtolower($filename), 'pdf') ? 'pdf' : 'imagen',
                    'nombre_original' => $filename,
                    'orden' => 1
                ]);

                $this->info("✅ Enlazado: $filename -> {$tema->nombre_tema} (1ra Voz Trompeta)");
                $count++;
            }
        }

        $this->info("------------------------------------------------");
        $this->info("¡Listo pes :vvvv! $count partituras arregladas.");
    }
}
