<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Asistencia;
use App\Models\ConvocatoriaEvento;
use App\Models\Evento;
use App\Models\Miembro;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AsistenciaStatsController extends Controller
{
    public function globalStats(Request $request)
    {
        $year = $request->input('year', date('Y'));
        $month = $request->input('month', date('n'));

        // 1. Heatmap Data (All year)
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

        // Resumen Mensual Actual
        $startOfMonth = Carbon::now()->startOfMonth()->toDateString();
        $endOfMonth = Carbon::now()->endOfMonth()->toDateString();

        $monthlyEvents = DB::table('eventos')
            ->whereBetween('fecha', [$startOfMonth, $endOfMonth])
            ->count();

        $monthlyAttendance = DB::table('asistencias')
            ->join('convocatoria_evento', 'asistencias.id_convocatoria', '=', 'convocatoria_evento.id_convocatoria')
            ->join('eventos', 'convocatoria_evento.id_evento', '=', 'eventos.id_evento')
            ->whereBetween('eventos.fecha', [$startOfMonth, $endOfMonth])
            ->select(
                DB::raw("COUNT(*) as total"),
                DB::raw("SUM(CASE WHEN asistencias.estado IN ('PUNTUAL', 'RETRASO') THEN 1 ELSE 0 END) as present")
            )
            ->first();

        $monthlyRate = $monthlyAttendance->total > 0 ? round(($monthlyAttendance->present / $monthlyAttendance->total) * 100) : 0;

        // Resumen Semanal Actual (Últimos 7 días)
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

        return response()->json([
            'heatmap' => $heatmapData,
            'monthly' => [
                'month_name' => Carbon::now()->translatedFormat('F'),
                'total_events' => $monthlyEvents,
                'attendance_rate' => $monthlyRate,
            ],
            'weekly' => $weekly
        ]);
    }

    public function memberStats($id, Request $request)
    {
        $miembro = Miembro::with(['instrumento', 'seccion'])->findOrFail($id);

        $history = DB::table('convocatoria_evento')
            ->join('eventos', 'convocatoria_evento.id_evento', '=', 'eventos.id_evento')
            ->leftJoin('asistencias', 'convocatoria_evento.id_convocatoria', '=', 'asistencias.id_convocatoria')
            ->where('convocatoria_evento.id_miembro', $id)
            ->select(
                'eventos.id_evento',
                'eventos.evento',
                'eventos.fecha',
                'eventos.hora',
                'asistencias.estado',
                'asistencias.hora_llegada'
            )
            ->orderBy('eventos.fecha', 'desc')
            ->orderBy('eventos.hora', 'desc')
            ->limit(20)
            ->get();

        // Calculate Streak
        $streak = 0;
        $today = Carbon::today()->toDateString();

        $pastEvents = $history->filter(function($row) use ($today) {
            return $row->fecha < $today;
        });

        foreach ($pastEvents as $record) {
            if (in_array($record->estado, ['PUNTUAL', 'RETRASO'])) {
                $streak++;
            } else if ($record->estado == 'FALTA' || is_null($record->estado)) {
                break;
            }
            if ($record->estado !== 'PUNTUAL' && $record->estado !== 'RETRASO' && $record->estado !== 'JUSTIFICADO') {
                break;
            }
        }

        $totalEvents = $pastEvents->count();
        $present = $pastEvents->whereIn('estado', ['PUNTUAL', 'RETRASO'])->count();
        $rate = $totalEvents > 0 ? round(($present / $totalEvents) * 100) : 0;

        return response()->json([
            'member' => [
                'id_miembro' => $miembro->id_miembro,
                'nombres' => $miembro->nombres,
                'apellidos' => $miembro->apellidos,
                'celular' => $miembro->celular,
                'instrumento' => $miembro->instrumento?->instrumento ?? null,
            ],
            'stats' => [
                'streak' => $streak,
                'attendance_rate' => $rate,
                'total_events' => $totalEvents,
                'present_count' => $present,
                'absent_count' => $pastEvents->where('estado', 'FALTA')->count(),
                'justified_count' => $pastEvents->where('estado', 'JUSTIFICADO')->count(),
            ],
            'history' => $history
        ]);
    }

    public function groupReport(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfYear()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->toDateString());
        $idSeccion = $request->input('id_seccion');

        $query = Miembro::with(['instrumento', 'seccion']);

        if ($idSeccion) {
            $query->where('id_seccion', $idSeccion);
        }

        $miembros = $query->get();

        $stats = DB::table('convocatoria_evento')
            ->join('eventos', 'convocatoria_evento.id_evento', '=', 'eventos.id_evento')
            ->leftJoin('asistencias', 'convocatoria_evento.id_convocatoria', '=', 'asistencias.id_convocatoria')
            ->whereBetween('eventos.fecha', [$startDate, $endDate])
            ->select(
                'convocatoria_evento.id_miembro',
                DB::raw('COUNT(*) as total_events'),
                DB::raw("SUM(CASE WHEN asistencias.estado IN ('PUNTUAL', 'RETRASO') THEN 1 ELSE 0 END) as present_count"),
                DB::raw("SUM(CASE WHEN asistencias.estado = 'FALTA' THEN 1 ELSE 0 END) as absent_count"),
                DB::raw("SUM(CASE WHEN asistencias.estado = 'JUSTIFICADO' THEN 1 ELSE 0 END) as justified_count"),
                DB::raw("SUM(CASE WHEN asistencias.estado IS NULL AND eventos.fecha < '" . date('Y-m-d') . "' THEN 1 ELSE 0 END) as unmarked_count")
            )
            ->groupBy('convocatoria_evento.id_miembro')
            ->get()
            ->keyBy('id_miembro');

        $report = $miembros->map(function ($m) use ($stats) {
            // Use array syntax or property syntax depending on how Miembro is handled
            $s = $stats->get($m->id_miembro) ?? (object)[
                'total_events' => 0,
                'present_count' => 0,
                'absent_count' => 0,
                'justified_count' => 0,
                'unmarked_count' => 0
            ];

            $rate = $s->total_events > 0 ? round(($s->present_count / $s->total_events) * 100) : 0;

            return [
                'id_miembro' => $m->id_miembro,
                'nombres' => $m->nombres,
                'apellidos' => $m->apellidos,
                'instrumento' => $m->instrumento?->instrumento ?? 'N/A',
                'seccion' => $m->seccion?->seccion ?? 'N/A',
                'total_events' => (int)$s->total_events,
                'present_count' => (int)$s->present_count,
                'absent_count' => (int)$s->absent_count,
                'justified_count' => (int)$s->justified_count,
                'unmarked_count' => (int)$s->unmarked_count,
                'rate' => $rate
            ];
        });

        // Sort by rate descending
        $report = $report->sortByDesc('rate')->values();

        // Calculate Group Summary
        $totalPresent = $report->sum('present_count');
        $totalEventsCount = $report->sum('total_events');
        $groupAverage = $totalEventsCount > 0 ? round(($totalPresent / $totalEventsCount) * 100) : 0;

        // Desertores: less than 50% but at least 3 opportunities
        $desertores = $report->filter(fn($r) => $r['total_events'] >= 3 && $r['rate'] < 50)->values();

        return response()->json([
            'report' => $report,
            'summary' => [
                'group_average' => $groupAverage,
                'desertores_count' => $desertores->count(),
                'total_members_in_report' => $miembros->count()
            ],
            'desertores' => $desertores,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'id_seccion' => $idSeccion
            ]
        ]);
    }
}

