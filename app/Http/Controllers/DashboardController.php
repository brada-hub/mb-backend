<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Miembro;
use App\Models\Evento;
use App\Models\Asistencia;
use App\Models\ConvocatoriaEvento;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function getStats()
    {
        $user = auth()->user();
        $rol = strtoupper($user->miembro->rol->rol ?? '');
        $isMusico = Str::contains($rol, 'MÚSICO') && !$user->is_super_admin;
        $isJefe = Str::contains($rol, ['JEFE', 'DELEGADO']) && !$user->is_super_admin;
        $isDirector = $rol === 'DIRECTOR' || $user->is_super_admin;

        // 1. Miembros Totales
        $queryMiembros = Miembro::query();
        if ($isJefe) {
            $queryMiembros->where('id_instrumento', $user->miembro->id_instrumento);
        } elseif ($isMusico) {
            $queryMiembros->where('id_miembro', $user->id_miembro);
        }

        $totalMiembros = $queryMiembros->count();
        $miembrosEsteMes = $queryMiembros->whereMonth('created_at', Carbon::now()->month)->count();

        // 2. Próximos Eventos (Próximos 7 días)
        $proximosEventos = Evento::where('fecha', '>=', Carbon::today()->toDateString())
            ->where('fecha', '<=', Carbon::today()->addDays(7)->toDateString())
            ->count();

        // 3. Eventos Hoy
        $eventosHoy = Evento::whereDate('fecha', Carbon::today())->count();

        // 4. Asistencia Promedio (Global para Admin, Personal para Músico)
        $queryConvocados = DB::table('convocatoria_evento')
            ->join('eventos', 'convocatoria_evento.id_evento', '=', 'eventos.id_evento')
            ->where('eventos.fecha', '<', Carbon::today()->toDateString())
            ->where('eventos.id_banda', $user->id_banda);

        $queryPresentes = DB::table('asistencias')
            ->join('convocatoria_evento', 'asistencias.id_convocatoria', '=', 'convocatoria_evento.id_convocatoria')
            ->join('eventos', 'convocatoria_evento.id_evento', '=', 'eventos.id_evento')
            ->where('eventos.fecha', '<', Carbon::today()->toDateString())
            ->where('eventos.id_banda', $user->id_banda)
            ->whereIn('asistencias.estado', ['PUNTUAL', 'RETRASO']);

        if ($isMusico) {
            $queryConvocados->where('convocatoria_evento.id_miembro', $user->miembro->id_miembro);
            $queryPresentes->where('convocatoria_evento.id_miembro', $user->miembro->id_miembro);
        } elseif ($isJefe) {
            $queryConvocados->join('miembros', 'convocatoria_evento.id_miembro', '=', 'miembros.id_miembro')
                           ->where('miembros.id_instrumento', $user->miembro->id_instrumento);
            $queryPresentes->join('miembros', 'convocatoria_evento.id_miembro', '=', 'miembros.id_miembro')
                           ->where('miembros.id_instrumento', $user->miembro->id_instrumento);
        }

        $totalConvocados = $queryConvocados->count();
        $totalPresentes = $queryPresentes->count();
        $asistenciaPromedio = $totalConvocados > 0 ? round(($totalPresentes / $totalConvocados) * 100) : 0;

        // 5. Heatmap Data (Restringido para Músicos/Jefes)
        $heatmapData = $this->getHeatmapData($isMusico, $user, $isJefe);

        // 6. User Specific Data (Hoy + Próximos Confirmados)
        $misEventos = collect();
        if ($user->miembro) {
            $misEventos = Evento::with('tipo')
                ->where('fecha', '>=', Carbon::today()->toDateString())
                ->whereHas('convocatorias', function($q) use ($user) {
                    $q->where('id_miembro', $user->miembro->id_miembro);
                })
                ->orderBy('fecha', 'asc')
                ->orderBy('hora', 'asc')
                ->get()
                ->map(function($evento) use ($user) {
                    $fechaStr = ($evento->fecha instanceof Carbon) ? $evento->fecha->format('Y-m-d') : $evento->fecha;
                    $horaEvento = Carbon::parse($fechaStr . ' ' . $evento->hora, 'America/La_Paz');
                    $ahora = Carbon::now('America/La_Paz');

                    $minAntes = $evento->tipo ? ($evento->tipo->minutos_antes_marcar ?? 15) : 15;
                    $minCierre = $evento->minutos_cierre ?? ($evento->tipo ? ($evento->tipo->minutos_cierre ?? 60) : 60);

                    $evento->puede_marcar = $ahora->between($horaEvento->copy()->subMinutes($minAntes), $horaEvento->copy()->addMinutes($minCierre));
                    $evento->es_hoy = Carbon::today()->toDateString() === $fechaStr;

                    $evento->asistencia = Asistencia::whereHas('convocatoria', function($q) use ($evento, $user) {
                        $q->where('id_evento', $evento->id_evento)->where('id_miembro', $user->miembro->id_miembro);
                    })->first();

                    return $evento;
                });
        }

        // 7. Personal Stats para Músicos
        $miAsistencia = 0;
        $miRacha = 0;
        $miembrosSeccion = 0;

        if ($user->miembro) {
            // Calcular asistencia personal
            $misConvocados = DB::table('convocatoria_evento')
                ->join('eventos', 'convocatoria_evento.id_evento', '=', 'eventos.id_evento')
                ->where('convocatoria_evento.id_miembro', $user->miembro->id_miembro)
                ->where('eventos.fecha', '<', Carbon::today()->toDateString())
                ->count();

            $misPresentes = DB::table('asistencias')
                ->join('convocatoria_evento', 'asistencias.id_convocatoria', '=', 'convocatoria_evento.id_convocatoria')
                ->join('eventos', 'convocatoria_evento.id_evento', '=', 'eventos.id_evento')
                ->where('convocatoria_evento.id_miembro', $user->miembro->id_miembro)
                ->where('eventos.fecha', '<', Carbon::today()->toDateString())
                ->whereIn('asistencias.estado', ['PUNTUAL', 'RETRASO'])
                ->count();

            $miAsistencia = $misConvocados > 0 ? round(($misPresentes / $misConvocados) * 100) : 0;

            // Calcular racha personal
            $misRecords = DB::table('convocatoria_evento')
                ->join('eventos', 'convocatoria_evento.id_evento', '=', 'eventos.id_evento')
                ->leftJoin('asistencias', 'convocatoria_evento.id_convocatoria', '=', 'asistencias.id_convocatoria')
                ->where('convocatoria_evento.id_miembro', $user->miembro->id_miembro)
                ->where('eventos.fecha', '<', Carbon::today()->toDateString())
                ->select('asistencias.estado', 'eventos.fecha')
                ->orderBy('eventos.fecha', 'desc')
                ->get();

            foreach ($misRecords as $rec) {
                if (in_array($rec->estado, ['PUNTUAL', 'RETRASO'])) {
                    $miRacha++;
                } else {
                    break;
                }
            }
        }

        // Miembros de sección para Jefe
        if ($isJefe && $user->miembro) {
            $miembrosSeccion = Miembro::where('id_instrumento', $user->miembro->id_instrumento)->count();
        }

        return response()->json([
            'stats' => [
                'miembros' => [
                    'total' => $isMusico ? 0 : $totalMiembros,
                    'nuevos_mes' => $isMusico ? 0 : $miembrosEsteMes
                ],
                'miembros_seccion' => $miembrosSeccion,
                'eventos' => [
                    'hoy' => $eventosHoy,
                    'proximos' => $proximosEventos,
                    'pendientes_formacion' => $isMusico ? 0 : Evento::where('fecha', '>=', Carbon::today()->toDateString())
                        ->where('estado', true)
                        ->whereHas('requerimientos')
                        ->get()
                        ->filter(function($ev) use ($isJefe, $user) {
                            if ($isJefe) {
                                $idInst = $user->miembro->id_instrumento;
                                $req = $ev->requerimientos->where('id_instrumento', $idInst)->first();
                                if (!$req) return false;
                                return $ev->convocatorias()->whereHas('miembro', function($q) use ($idInst) {
                                    $q->where('id_instrumento', $idInst);
                                })->count() < $req->cantidad_necesaria;
                            }
                            return $ev->convocatorias->count() < $ev->requerimientos->sum('cantidad_necesaria');
                        })->count()
                ],
                'asistencia' => [
                    'promedio' => $asistenciaPromedio,
                    'promedio_mes' => $asistenciaPromedio
                ]
            ],
            'mi_asistencia' => $miAsistencia,
            'mi_racha' => $miRacha,
            'suscripcion' => $user->id_banda ? [
                'plan' => $user->banda?->plan ?? 'BASIC',
                'max_miembros' => $user->banda?->max_miembros ?? 15,
                'uso_miembros' => Miembro::count(),
                'pro_activo' => in_array(strtoupper($user->banda?->plan), ['PREMIUM', 'PRO', 'MONSTER'])
            ] : null,
            'heatmap' => $heatmapData,
            'mis_eventos' => $misEventos,
            'top_streaks' => $isMusico ? [] : $this->calculateTopStreaks($isJefe, $user),
            'weekly' => $this->getWeeklyData($isMusico, $user, $isJefe)
        ]);
    }

    private function calculateTopStreaks($isJefe = false, $user = null)
    {
        // Get all member IDs
        $miembrosQuery = Miembro::with('instrumento');
        if ($isJefe) {
            $miembrosQuery->where('id_instrumento', $user->miembro->id_instrumento);
        }
        $miembros = $miembrosQuery->get();
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

    private function getWeeklyData($isMusico = false, $user = null, $isJefe = false)
    {
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();

        $query = DB::table('eventos')
            ->leftJoin('convocatoria_evento', 'eventos.id_evento', '=', 'convocatoria_evento.id_evento')
            ->leftJoin('asistencias', 'convocatoria_evento.id_convocatoria', '=', 'asistencias.id_convocatoria')
            ->where('eventos.id_banda', $user->id_banda ?? 0)
            ->whereBetween('eventos.fecha', [$startOfWeek->toDateString(), $endOfWeek->toDateString()]);

        if ($isMusico && $user && $user->miembro) {
            $query->where('convocatoria_evento.id_miembro', $user->miembro->id_miembro);
        } elseif ($isJefe) {
            $query->join('miembros', 'convocatoria_evento.id_miembro', '=', 'miembros.id_miembro')
                  ->where('miembros.id_instrumento', $user->miembro->id_instrumento);
        }

        $data = $query->select(
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

    private function getHeatmapData($isMusico = false, $user = null, $isJefe = false)
    {
        $query = DB::table('asistencias')
            ->join('convocatoria_evento', 'asistencias.id_convocatoria', '=', 'convocatoria_evento.id_convocatoria')
            ->join('eventos', 'convocatoria_evento.id_evento', '=', 'eventos.id_evento')
            ->where('eventos.id_banda', $user->id_banda ?? 0)
            ->where('asistencias.estado', '!=', 'FALTA')
            ->select(
                DB::raw('DAYOFWEEK(eventos.fecha) - 1 as day'),
                DB::raw('HOUR(eventos.hora) as hour'),
                DB::raw('count(*) as value')
            );

        if ($isMusico && $user->miembro) {
            $query->where('convocatoria_evento.id_miembro', $user->miembro->id_miembro);
        } elseif ($isJefe) {
            $query->join('miembros', 'convocatoria_evento.id_miembro', '=', 'miembros.id_miembro')
                  ->where('miembros.id_instrumento', $user->miembro->id_instrumento);
        }

        return $query->groupBy('day', 'hour')->get()->map(function($item) {
            return [
                'day' => (int)$item->day,
                'hour' => (int)$item->hour,
                'value' => (int)$item->value
            ];
        });
    }
}
