<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Miembro;
use App\Models\Evento;
use App\Models\Asistencia;
use App\Models\ConvocatoriaEvento;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function getStats()
    {
        // 1. Miembros Totales
        $totalMiembros = Miembro::count();
        $miembrosEsteMes = Miembro::whereMonth('created_at', Carbon::now()->month)
            ->count();

        // 2. Próximos Eventos (Próximos 7 días)
        $proximosEventos = Evento::where('fecha', '>=', Carbon::today()->toDateString())
            ->where('fecha', '<=', Carbon::today()->addDays(7)->toDateString())
            ->count();

        // 3. Eventos Hoy
        $eventosHoy = Evento::whereDate('fecha', Carbon::today())->count();

        // 4. Eventos Este Mes
        $eventosEsteMes = Evento::whereYear('fecha', Carbon::now()->year)
            ->whereMonth('fecha', Carbon::now()->month)
            ->count();

        // 5. Asistencia Promedio Global
        $totalConvocados = DB::table('convocatoria_evento')
            ->join('eventos', 'convocatoria_evento.id_evento', '=', 'eventos.id_evento')
            ->where('eventos.fecha', '<', Carbon::today()->toDateString())
            ->count();

        $totalPresentes = DB::table('asistencias')
            ->join('convocatoria_evento', 'asistencias.id_convocatoria', '=', 'convocatoria_evento.id_convocatoria')
            ->join('eventos', 'convocatoria_evento.id_evento', '=', 'eventos.id_evento')
            ->where('eventos.fecha', '<', Carbon::today()->toDateString())
            ->whereIn('asistencias.estado', ['PUNTUAL', 'RETRASO'])
            ->count();

        $asistenciaPromedio = $totalConvocados > 0 ? round(($totalPresentes / $totalConvocados) * 100) : 0;

        // 6. Asistencia Promedio Mensual
        $startOfMonth = Carbon::now()->startOfMonth()->toDateString();
        $endOfMonth = Carbon::now()->endOfMonth()->toDateString();

        $convocadosMes = DB::table('convocatoria_evento')
            ->join('eventos', 'convocatoria_evento.id_evento', '=', 'eventos.id_evento')
            ->whereBetween('eventos.fecha', [$startOfMonth, $endOfMonth])
            ->count();

        $presentesMes = DB::table('asistencias')
            ->join('convocatoria_evento', 'asistencias.id_convocatoria', '=', 'convocatoria_evento.id_convocatoria')
            ->join('eventos', 'convocatoria_evento.id_evento', '=', 'eventos.id_evento')
            ->whereBetween('eventos.fecha', [$startOfMonth, $endOfMonth])
            ->whereIn('asistencias.estado', ['PUNTUAL', 'RETRASO'])
            ->count();

        $asistenciaPromedioMes = $convocadosMes > 0 ? round(($presentesMes / $convocadosMes) * 100) : 0;

        // 7. Top Streaks (Músicos con mejores rachas)
        $topStreaks = $this->calculateTopStreaks();

        // 8. Weekly Data
        $weeklyData = $this->getWeeklyData();

        // 9. Heatmap Data
        $year = date('Y');
        $heatmapData = DB::table('eventos')
            ->join('convocatoria_evento', 'eventos.id_evento', '=', 'convocatoria_evento.id_evento')
            ->leftJoin('asistencias', 'convocatoria_evento.id_convocatoria', '=', 'asistencias.id_convocatoria')
            ->select(
                'eventos.fecha as date',
                DB::raw("STRING_AGG(DISTINCT eventos.evento, ', ') as event_names"),
                DB::raw('COUNT(convocatoria_evento.id_convocatoria) as total_convocados'),
                DB::raw("SUM(CASE WHEN asistencias.estado IN ('PUNTUAL', 'RETRASO') THEN 1 ELSE 0 END) as total_presentes")
            )
            ->whereYear('eventos.fecha', $year)
            ->groupBy('eventos.fecha')
            ->get()
            ->map(function ($day) {
                $percentage = $day->total_convocados > 0 ? ($day->total_presentes / $day->total_convocados) : 0;
                $intensity = 0;
                if ($day->total_convocados > 0) {
                    if ($percentage == 0) $intensity = 0;
                    elseif ($percentage < 0.4) $intensity = 1;
                    elseif ($percentage < 0.7) $intensity = 2;
                    elseif ($percentage < 0.9) $intensity = 3;
                    else $intensity = 4;
                }
                return [
                    'date' => $day->date,
                    'count' => (int)$day->total_presentes,
                    'total' => (int)$day->total_convocados,
                    'percentage' => round($percentage * 100),
                    'events' => $day->event_names,
                    'level' => $intensity
                ];
            });

        // 10. User Specific Data (If authenticated)
        $misEventos = [];
        $user = auth()->user();
        if ($user && $user->miembro) {
            $misEventos = Evento::with('tipo')
                ->whereDate('fecha', Carbon::today())
                ->whereHas('convocatorias', function($q) use ($user) {
                    $q->where('id_miembro', $user->miembro->id_miembro)
                      ->where('confirmado_por_director', true);
                })
                ->get()
                ->map(function($evento) use ($user) {
                    $fechaStr = ($evento->fecha instanceof Carbon) ? $evento->fecha->format('Y-m-d') : $evento->fecha;
                    $horaEvento = Carbon::parse($fechaStr . ' ' . $evento->hora, 'America/La_Paz');
                    $ahora = Carbon::now('America/La_Paz');
                    $tipo = $evento->tipo;

                    $minAntes = $tipo ? ($tipo->minutos_antes_marcar ?? 15) : 15;
                    $minCierre = $evento->minutos_cierre ?? ($tipo ? ($tipo->minutos_cierre ?? 60) : 60);

                    $limiteInferior = $horaEvento->copy()->subMinutes($minAntes);
                    $limiteSuperior = $horaEvento->copy()->addMinutes($minCierre);

                    $evento->puede_marcar = $ahora->greaterThanOrEqualTo($limiteInferior) && $ahora->lessThanOrEqualTo($limiteSuperior);
                    $evento->asistencia = Asistencia::whereHas('convocatoria', function($q) use ($evento, $user) {
                        $q->where('id_evento', $evento->id_evento)
                          ->where('id_miembro', $user->miembro->id_miembro);
                    })->first();

                    return $evento;
                });
        }

        return response()->json([
            'stats' => [
                'miembros' => [
                    'total' => $totalMiembros,
                    'nuevos_mes' => $miembrosEsteMes
                ],
                'eventos' => [
                    'proximos' => $proximosEventos,
                    'hoy' => $eventosHoy,
                    'este_mes' => $eventosEsteMes
                ],
                'asistencia' => [
                    'promedio' => $asistenciaPromedio,
                    'promedio_mes' => $asistenciaPromedioMes
                ],
                'finanzas' => [
                    'mes' => 0,
                    'tendencia' => 0
                ]
            ],
            'eventos_hoy' => Evento::whereDate('fecha', Carbon::today())->get(),
            'proximo_evento' => Evento::where('fecha', '>=', Carbon::today())->orderBy('fecha', 'asc')->orderBy('hora', 'asc')->first(),
            'heatmap' => $heatmapData,
            'top_streaks' => $topStreaks,
            'weekly' => $weeklyData,
            'mis_eventos' => $misEventos
        ]);
    }

    private function calculateTopStreaks()
    {
        // Get all member IDs
        $miembros = Miembro::with('instrumento')->get();
        if ($miembros->isEmpty()) return [];

        // Pre-fetch all past assistances for these members to avoid N+1
        $allAsistencias = DB::table('convocatoria_evento')
            ->join('eventos', 'convocatoria_evento.id_evento', '=', 'eventos.id_evento')
            ->leftJoin('asistencias', 'convocatoria_evento.id_convocatoria', '=', 'asistencias.id_convocatoria')
            ->where('eventos.fecha', '<', Carbon::today()->toDateString())
            ->select('convocatoria_evento.id_miembro', 'asistencias.estado', 'eventos.fecha')
            ->orderBy('eventos.fecha', 'desc')
            ->get()
            ->groupBy('id_miembro');

        $streaks = [];

        foreach ($miembros as $miembro) {
            $records = $allAsistencias->get($miembro->id_miembro, collect());
            $streak = 0;

            foreach ($records as $rec) {
                if (in_array($rec->estado, ['PUNTUAL', 'RETRASO'])) {
                    $streak++;
                } else if ($rec->estado === 'FALTA' || is_null($rec->estado)) {
                    // Stop streak on absence or unmarked past events
                    break;
                }
                // JUSTIFICADO doesn't count towards streak but doesn't break it?
                // Usually it breaks it/pauses it. Let's assume only present counts.
            }

            if ($streak > 0) {
                $streaks[] = [
                    'id_miembro' => $miembro->id_miembro,
                    'nombres' => $miembro->nombres,
                    'apellidos' => $miembro->apellidos,
                    'instrumento' => $miembro->instrumento?->instrumento ?? 'N/A',
                    'streak' => $streak
                ];
            }
        }

        usort($streaks, fn($a, $b) => $b['streak'] - $a['streak']);
        return array_slice($streaks, 0, 10);
    }

    private function getWeeklyData()
    {
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();

        $data = DB::table('eventos')
            ->leftJoin('convocatoria_evento', 'eventos.id_evento', '=', 'convocatoria_evento.id_evento')
            ->leftJoin('asistencias', 'convocatoria_evento.id_convocatoria', '=', 'asistencias.id_convocatoria')
            ->whereBetween('eventos.fecha', [$startOfWeek->toDateString(), $endOfWeek->toDateString()])
            ->select(
                'eventos.fecha',
                DB::raw("COUNT(convocatoria_evento.id_convocatoria) as total"),
                DB::raw("SUM(CASE WHEN asistencias.estado IN ('PUNTUAL', 'RETRASO') THEN 1 ELSE 0 END) as present")
            )
            ->groupBy('eventos.fecha')
            ->get()
            ->keyBy('fecha');

        $weekly = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $startOfWeek->copy()->addDays($i)->toDateString();
            if (isset($data[$date])) {
                $weekly[] = [
                    'fecha' => $date,
                    'total' => (int)$data[$date]->total,
                    'present' => (int)$data[$date]->present
                ];
            } else {
                $weekly[] = [
                    'fecha' => $date,
                    'total' => 0,
                    'present' => 0
                ];
            }
        }

        return $weekly;
    }
}
