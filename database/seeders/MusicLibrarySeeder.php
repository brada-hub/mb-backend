<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Genero;
use App\Models\Tema;
use App\Models\VozInstrumental;
use App\Models\Instrumento;
use App\Models\Recurso;
use App\Models\Archivo;

class MusicLibrarySeeder extends Seeder
{
    public function run(): void
    {
        // 1. Generos
        $generos = [
            'MORENADA',
            'CAPORAL',
            'TINKUS',
            'SALAY',
            'TOBAS',
            'DIABLADA',
            'CUECA',
            'HUAYÑO'
        ];

        foreach ($generos as $g) {
            Genero::updateOrCreate(['nombre_genero' => $g]);
        }

        // 2. Temas de ejemplo para Caporal
        $caporal = Genero::where('nombre_genero', 'CAPORAL')->first();
        $temasCaporal = [
            'SOY CAPORAL',
            'HERIDA',
            'OYE MUJER',
            'FORZADOS DE BOLIVIA'
        ];

        foreach ($temasCaporal as $t) {
            Tema::updateOrCreate([
                'id_genero' => $caporal->id_genero,
                'nombre_tema' => $t
            ]);
        }

        // 3. Temas de ejemplo para Morenada
        $morenada = Genero::where('nombre_genero', 'MORENADA')->first();
        $temasMorenada = [
            'AZUL Y AMARILLO',
            'LA ALEGRIA DE MI VIDA',
            'CENTRALISTA DE CORAZON',
            'A MI BOLIVIA'
        ];

        foreach ($temasMorenada as $t) {
            Tema::updateOrCreate([
                'id_genero' => $morenada->id_genero,
                'nombre_tema' => $t
            ]);
        }

        // 4. Temas de ejemplo para Tinkus
        $tinkus = Genero::where('nombre_genero', 'TINKUS')->first();
        $temasTinkus = [
            'EL LATIDO DE MI PECHO',
            'FUERZA TINKU'
        ];

        foreach ($temasTinkus as $t) {
            Tema::updateOrCreate([
                'id_genero' => $tinkus->id_genero,
                'nombre_tema' => $t
            ]);
        }

        // 5. Voces Instrumentales comunes
        $vocesList = [
            '1RA VOZ',
            '2DA VOZ',
            '3RA VOZ',
            'GUIA SOLO',
            'FULL SCORE',
            'ARRANGEMENT'
        ];

        foreach ($vocesList as $v) {
            VozInstrumental::updateOrCreate(['nombre_voz' => $v]);
        }

        // 6. Sembrar algunos Recursos (Partituras y Audios)
        // Tomaremos el tema 'SOY CAPORAL' para meterle de todo
        $soyCaporal = Tema::where('nombre_tema', 'SOY CAPORAL')->first();
        $v1 = VozInstrumental::where('nombre_voz', '1RA VOZ')->first();
        $v2 = VozInstrumental::where('nombre_voz', '2DA VOZ')->first();
        $score = VozInstrumental::where('nombre_voz', 'FULL SCORE')->first();

        $trompeta = Instrumento::where('instrumento', 'TROMPETA')->first();
        $trombon = Instrumento::where('instrumento', 'TROMBÓN')->first();
        $platillo = Instrumento::where('instrumento', 'PLATILLO')->first();

        if ($soyCaporal && $trompeta) {
            // Recurso 1: Trompeta 1ra Voz
            $rec1 = Recurso::create([
                'id_tema' => $soyCaporal->id_tema,
                'id_instrumento' => $trompeta->id_instrumento,
                'id_voz' => $v1->id_voz
            ]);

            Archivo::create([
                'id_recurso' => $rec1->id_recurso,
                'url_archivo' => 'https://res.cloudinary.com/demo/image/upload/v1312461204/sample.jpg', // Placeholder
                'tipo' => 'imagen',
                'nombre_original' => 'TROMPETA_1_SOY_CAPORAL.JPG',
                'orden' => 1
            ]);

            Archivo::create([
                'id_recurso' => $rec1->id_recurso,
                'url_archivo' => 'https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf', // Placeholder
                'tipo' => 'audio',
                'nombre_original' => 'Audio Guía Trompeta',
                'orden' => 99
            ]);
        }

        if ($soyCaporal && $trombon) {
            // Recurso 2: Trombón 2da Voz
            $rec2 = Recurso::create([
                'id_tema' => $soyCaporal->id_tema,
                'id_instrumento' => $trombon->id_instrumento,
                'id_voz' => $v2->id_voz
            ]);

            Archivo::create([
                'id_recurso' => $rec2->id_recurso,
                'url_archivo' => 'https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf',
                'tipo' => 'pdf',
                'nombre_original' => 'TROMBON_2_SOY_CAPORAL.PDF',
                'orden' => 1
            ]);
        }

        // Un tema de Morenada con Full Score
        $azulYAmarillo = Tema::where('nombre_tema', 'AZUL Y AMARILLO')->first();
        if ($azulYAmarillo && $platillo) {
            $rec3 = Recurso::create([
                'id_tema' => $azulYAmarillo->id_tema,
                'id_instrumento' => $platillo->id_instrumento,
                'id_voz' => $score->id_voz
            ]);

            Archivo::create([
                'id_recurso' => $rec3->id_recurso,
                'url_archivo' => 'https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf',
                'tipo' => 'pdf',
                'nombre_original' => 'SCORE_GENERAL_AZUL.PDF',
                'orden' => 1
            ]);
        }
    }
}
