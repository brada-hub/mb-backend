<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Evento;
use App\Models\TipoEvento;
use App\Models\Miembro;
use App\Models\ConvocatoriaEvento;
use App\Models\Asistencia;
use App\Models\Banda;
use Faker\Factory as Faker;
use Carbon\Carbon;

class EventosSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create('es_BO');

        // 1. Obtener la banda "monster-band"
        $banda = Banda::where('slug', 'monster-band')->first();
        if (!$banda) {
            $this->command->error('No se encontró la banda "monster-band". Corre InitialCatalogSeeder primero.');
            return;
        }
        $idBanda = $banda->id_banda;

        // 2. Obtener miembros de la banda
        $miembros = Miembro::where('id_banda', $idBanda)->get();
        if ($miembros->isEmpty()) {
            $this->command->error('No hay miembros en la banda. Corre MiembrosSeeder primero.');
            return;
        }

        // 3. Obtener tipos de evento
        $tipos = TipoEvento::all();
        if ($tipos->isEmpty()) {
            $this->command->error('No hay tipos de evento. Corre TipoEventoSeeder primero.');
            return;
        }

        // 4. Elegir algunos miembros para que tengan "racha" (asistencia perfecta)
        $miembrosConRacha = $miembros->random(min(5, $miembros->count()));

        $this->command->info('Sembrando eventos, asistencias y pagos (con rachas)...');

        // 5. Crear 20 eventos en el pasado para asegurar rachas largas
        for ($i = 0; $i < 25; $i++) {
            // Generar fechas en orden cronológico inverso para el seeder,
            // pero el Controller ordena por fecha desc, así que está bien.
            // Para asegurar rachas, mejor creamos eventos en los últimos 30 días.
            $fecha = Carbon::now()->subDays(30 - $i); // De hace 30 días hacia adelante
            $esPasado = $fecha->isPast();

            $tipo = $tipos->random();
            $esRemunerado = ($tipo->evento === 'CONTRATO' || $tipo->evento === 'BANDIN');

            $evento = Evento::create([
                'evento' => strtoupper($tipo->evento . ' - ' . $faker->words(2, true)),
                'fecha' => $fecha->format('Y-m-d'),
                'hora' => $faker->time('H:i'),
                'latitud' => -16.5 + ($faker->randomFloat(6, -0.1, 0.1)),
                'longitud' => -68.1 + ($faker->randomFloat(6, -0.1, 0.1)),
                'direccion' => strtoupper($faker->streetAddress),
                'radio' => 100,
                'estado' => true,
                'id_tipo_evento' => $tipo->id_tipo_evento,
                'ingreso_total_contrato' => $esRemunerado ? $faker->numberBetween(1000, 5000) : 0,
                'presupuesto_limite_sueldos' => $esRemunerado ? $faker->numberBetween(500, 2500) : 0,
                'version_evento' => 1,
                'minutos_tolerancia' => 15,
                'minutos_cierre' => 30,
                'asistencia_cerrada' => $esPasado,
                'remunerado' => $esRemunerado,
                'monto_sugerido' => $esRemunerado ? $faker->randomElement([50, 100, 150, 200]) : 0,
                'id_banda' => $idBanda
            ]);

            // Convocar a todos los "miembros con racha" + algunos aleatorios
            $convocadosExtra = $miembros->whereNotIn('id_miembro', $miembrosConRacha->pluck('id_miembro'))
                ->random($faker->numberBetween(5, 15));

            $todosConvocados = $miembrosConRacha->concat($convocadosExtra);

            foreach ($todosConvocados as $miembro) {
                $convocatoria = ConvocatoriaEvento::create([
                    'id_evento' => $evento->id_evento,
                    'id_miembro' => $miembro->id_miembro,
                    'confirmado_por_director' => true,
                    'pagado' => $esPasado && $esRemunerado ? $faker->boolean(70) : false,
                    'fecha_pago' => null
                ]);

                if ($convocatoria->pagado) {
                    $convocatoria->update(['fecha_pago' => $fecha->copy()->addDays($faker->numberBetween(1, 5))]);
                }

                if ($esPasado) {
                    $llegada = Carbon::parse($evento->hora);

                    // Si es miembro con racha, 100% PUNTUAL
                    if ($miembrosConRacha->contains('id_miembro', $miembro->id_miembro)) {
                        $estado = 'PUNTUAL';
                        $retraso = 0;
                        $horaMarcado = $llegada->copy()->subMinutes($faker->numberBetween(5, 15));
                    } else {
                        $rand = $faker->numberBetween(1, 100);
                        if ($rand < 60) {
                            $estado = 'PUNTUAL';
                            $retraso = 0;
                            $horaMarcado = $llegada->copy()->subMinutes($faker->numberBetween(5, 15));
                        } elseif ($rand < 85) {
                            $estado = 'RETRASO';
                            $retraso = $faker->numberBetween(1, 30);
                            $horaMarcado = $llegada->copy()->addMinutes($retraso);
                        } else {
                            $estado = 'FALTA';
                            $retraso = 0;
                            $horaMarcado = null;
                        }
                    }

                    if ($horaMarcado) {
                        Asistencia::create([
                            'id_convocatoria' => $convocatoria->id_convocatoria,
                            'hora_llegada' => $horaMarcado->format('H:i:s'),
                            'minutos_retraso' => $retraso,
                            'estado' => $estado,
                            'latitud_marcado' => $evento->latitud,
                            'longitud_marcado' => $evento->longitud,
                            'fecha_sincronizacion' => $fecha->format('Y-m-d H:i:s'),
                            'observacion' => $estado === 'RETRASO' ? 'Tráfico intenso' : null
                        ]);
                    } else {
                        Asistencia::create([
                            'id_convocatoria' => $convocatoria->id_convocatoria,
                            'hora_llegada' => null,
                            'minutos_retraso' => 0,
                            'estado' => $estado,
                            'observacion' => 'No asistió'
                        ]);
                    }
                }
            }
        }

        $this->command->info('¡Sembrado de eventos exitoso con rachas de asistencia!');
    }
}
