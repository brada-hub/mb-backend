<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RealDemoDataSeeder extends Seeder
{
    public function run()
    {
        $this->command->info("Iniciando con DB puro...");

        $bandaId = DB::table('bandas')->where('slug', 'monster-band')->value('id_banda');
        if (!$bandaId) {
            $this->command->error("Banda no encontrada");
            return;
        }

        // CLEANUP January 2026
        $eventIds = DB::table('eventos')
            ->where('id_banda', $bandaId)
            ->whereBetween('fecha', ['2026-01-01', '2026-01-31'])
            ->pluck('id_evento');

        if ($eventIds->isNotEmpty()) {
            DB::table('asistencias')->whereIn('id_convocatoria', function($q) use ($eventIds) {
                $q->select('id_convocatoria')->from('convocatoria_evento')->whereIn('id_evento', $eventIds);
            })->delete();
            DB::table('convocatoria_evento')->whereIn('id_evento', $eventIds)->delete();
            DB::table('eventos')->whereIn('id_evento', $eventIds)->delete();
        }

        // ENSURE 2 MEMBERS PER INSTRUMENT
        $instrumentos = DB::table('instrumentos')->where('id_banda', $bandaId)->get();
        foreach ($instrumentos as $inst) {
            $count = DB::table('miembros')->where('id_instrumento', $inst->id_instrumento)->count();
            while ($count < 2) {
                DB::table('miembros')->insert([
                    'nombres' => "TEST {$inst->instrumento}",
                    'apellidos' => "DEMO " . ($count + 1),
                    'ci' => rand(6000000, 9999999),
                    'celular' => '7' . rand(1000000, 9999999),
                    'id_seccion' => $inst->id_seccion,
                    'id_instrumento' => $inst->id_instrumento,
                    'id_rol' => 4, // MUSICO
                    'id_categoria' => 1,
                    'id_banda' => $bandaId,
                    'fecha' => '2000-01-01',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                $count++;
            }
        }

        $allMembers = DB::table('miembros')->where('id_banda', $bandaId)->get();

        $types = [
            'ENSAYO' => DB::table('tipos_evento')->where('evento', 'ENSAYO')->value('id_tipo_evento'),
            'CONTRATO' => DB::table('tipos_evento')->where('evento', 'CONTRATO')->value('id_tipo_evento'),
            'BANDIN' => DB::table('tipos_evento')->where('evento', 'BANDIN')->value('id_tipo_evento'),
        ];

        // Dates requested: 4 Ensayos (Tue/Thu), 2 Contracts (Sat/Sun), 1 Bandin (Sat)
        $dates = [
            '2026-01-13' => ['type' => $types['ENSAYO'], 'name' => 'ENSAYO MARTES', 'rem' => 0],
            '2026-01-15' => ['type' => $types['ENSAYO'], 'name' => 'ENSAYO JUEVES', 'rem' => 0],
            '2026-01-20' => ['type' => $types['ENSAYO'], 'name' => 'ENSAYO MARTES 2', 'rem' => 0],
            '2026-01-22' => ['type' => $types['ENSAYO'], 'name' => 'ENSAYO JUEVES 2', 'rem' => 0],
            '2026-01-24' => ['type' => $types['CONTRATO'], 'name' => 'CONTRATO SABADO', 'rem' => 1],
            '2026-01-25' => ['type' => $types['CONTRATO'], 'name' => 'CONTRATO DOMINGO', 'rem' => 1],
            '2026-01-17' => ['type' => $types['BANDIN'], 'name' => 'BANDIN ESPECIAL', 'rem' => 1],
        ];

        foreach ($dates as $date => $info) {
            $idEvento = DB::table('eventos')->insertGetId([
                'evento' => $info['name'],
                'id_tipo_evento' => $info['type'],
                'fecha' => $date,
                'hora' => '19:00:00',
                'id_banda' => $bandaId,
                'estado' => 1, // true (activo)
                'asistencia_cerrada' => 1,
                'remunerado' => $info['rem'],
                'monto_sugerido' => $info['rem'] ? 150 : 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            foreach ($allMembers as $member) {
                $idConv = DB::table('convocatoria_evento')->insertGetId([
                    'id_evento' => $idEvento,
                    'id_miembro' => $member->id_miembro,
                    'confirmado_por_miembro' => 1,
                    'confirmado_por_director' => 1,
                    'pagado' => 0,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                // Lógica de asistencia variada
                $rand = rand(1, 100);
                if ($rand < 60) {
                    $estado = 'PUNTUAL';
                    $retraso = 0;
                    $hora = '18:55:00';
                } elseif ($rand < 80) {
                    $estado = 'RETRASO';
                    $retraso = rand(5, 30);
                    $hora = Carbon::parse('19:00:00')->addMinutes($retraso)->format('H:i:s');
                } elseif ($rand < 90) {
                    $estado = 'FALTA';
                    $retraso = 0;
                    $hora = null;
                } else {
                    $estado = 'JUSTIFICADO';
                    $retraso = 0;
                    $hora = null;
                }

                DB::table('asistencias')->insert([
                    'id_convocatoria' => $idConv,
                    'estado' => $estado,
                    'hora_llegada' => $hora,
                    'minutos_retraso' => $retraso,
                    'fecha_sincronizacion' => $date . ' 19:00:00',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }

        $this->command->info("¡Listo! Datos generados exitosamente para Enero 2026.");
    }
}
