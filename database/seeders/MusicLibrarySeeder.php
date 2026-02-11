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
        // Obtener Banda Principal
        $banda = \App\Models\Banda::where('slug', 'monster')->first();
        $idBanda = $banda ? $banda->id_banda : null;

        if (!$idBanda) {
            $this->command->error("Banda 'monster' no encontrada. Abortando seeder de biblioteca.");
            return;
        }

        // 1. Voces Instrumentales (Globales)
        $vocesList = [
            '1RA VOZ',
            '2DA VOZ',
            '3RA VOZ',
            '8VA VOZ',
            'GENERAL'
        ];

        foreach ($vocesList as $v) {
            VozInstrumental::updateOrCreate(['nombre_voz' => $v]);
        }

        // 2. Generos (Asociados a la banda)
        $generos = [
            ['nombre' => 'DIABLADA', 'img' => 'genres/diablada.png', 'p' => '#800000', 's' => '#330000'],
            ['nombre' => 'CAPORAL', 'img' => 'genres/caporal.png', 'p' => '#4b5563', 's' => '#1f2937'],
            ['nombre' => 'TOBAS', 'img' => 'genres/tobas.png', 'p' => '#854d0e', 's' => '#422006'],
            ['nombre' => 'TINKUS', 'img' => 'genres/tinkus.png', 'p' => '#065f46', 's' => '#064e3b'],
            ['nombre' => 'MORENADA', 'img' => 'genres/morenada.png', 'p' => '#1e3a8a', 's' => '#1e1b4b'],
            ['nombre' => 'SALAY', 'img' => 'genres/salay.png', 'p' => '#581c87', 's' => '#3b0764'],
        ];

        foreach ($generos as $g) {
            Genero::updateOrCreate(
                ['nombre_genero' => $g['nombre']],
                [
                    'banner_opcional' => $g['img'],
                    'color_primario' => $g['p'],
                    'color_secundario' => $g['s'],
                    'id_banda' => $idBanda
                ]
            );
        }

        // 3. Temas de Caporal con Auto-Carga de Partituras (Modo Producción)
        // Solo carga si los archivos realmente existen en el disco
        $caporal = Genero::where('nombre_genero', 'CAPORAL')->first();
        $v1 = VozInstrumental::where('nombre_voz', '1RA VOZ')->first();
        $trompeta = Instrumento::where('instrumento', 'TROMPETA')->first();

        $temasCaporal = [
            'INTRO PEREGRINO', 'DOS PALOMITAS', 'CHEVERE QUE CHE', 'BELLEZAS Y RAUDALES',
            'CUANDO PASA SS', '40 AÑOS SS', 'DATE EL GUSTO', 'AMOROSA PALOMITA',
            'QUIEN TE HA DICHO', 'HASTA EL AMANECER', 'AY ROSITA', 'SIENTO YO EN EL ALMA',
            'CHACA CHACA', 'TU MI VIDA ERES TU', 'AZUL Y ROJO', 'ME ENAMORÉ DE UN IMPOSIBLE',
            'LOCO DE AMOR', 'PORQUE ME ENAMORE DE TI', 'VENENO PARA OLVIDAR', 'QUEMA QUEMA',
            'AMOR JOVEN', 'PARECE QUE VA A LLOVER', 'SIN LEY', 'TE HE PROMETIDO',
            'SOY POTOSI', 'SAYA SENSUAL', 'COCHALITA', 'PROMETIMOS NO LLORAR',
            'ME SOBRAN LAS PALABRAS', 'BELLA', 'SECRETO AMOR', 'DULCE COMPAÑERA',
            'SAYA AFRODISIACA', 'KUTIMUY', 'JILGUERO FLORES', 'BAILE CALIENTE',
            'TU ABANDONO', 'PALOMA DEL ALMA MIA', 'UNA POR OTRA', 'TOMARÉ PARA OLVIDAR',
            'DESDE LEJES HE VENIDO', 'QUIZAS SI QUIZAS NO', 'POR ELLA', 'SAYA DE COCHABAMBA',
            'MI MORENA DE SAN SIMON', 'LA PICARA', 'HERIDA', 'COMO HAS HECHO',
            'AMAMÉ', 'EL REENCUENTRO', 'FABIOLA', 'TE JURO QUE TE AMO', 'VIVIR JUNTO A TÍ'
        ];

        foreach ($temasCaporal as $t) {
            $tema = Tema::updateOrCreate(['id_genero' => $caporal->id_genero, 'nombre_tema' => $t], ['id_banda' => $idBanda]);

            // Sistema Inteligente de Carga de Assets
            $validExtensions = ['png', 'PNG', 'jpg', 'JPG', 'pdf', 'PDF'];
            $found = false;

            foreach ($validExtensions as $ext) {
                $fileName = $t . "." . $ext;
                $publicPath = "partituras/caporal/" . $fileName;
                $absolutePath = public_path($publicPath);

                if (file_exists($absolutePath)) {
                    if ($trompeta && $v1) {
                        $recurso = Recurso::updateOrCreate([
                            'id_tema' => $tema->id_tema,
                            'id_instrumento' => $trompeta->id_instrumento,
                            'id_voz' => $v1->id_voz
                        ]);

                        Archivo::updateOrCreate(
                            ['id_recurso' => $recurso->id_recurso, 'nombre_original' => $fileName],
                            [
                                'url_archivo' => url($publicPath),
                                'tipo' => (in_array(strtolower($ext), ['png', 'jpg'])) ? 'imagen' : 'pdf',
                                'orden' => 1
                            ]
                        );
                        $found = true;
                    }
                    break;
                }
            }
        }
    }
}
