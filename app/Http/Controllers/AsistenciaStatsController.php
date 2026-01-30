<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Asistencia;
use App\Models\ConvocatoriaEvento;
use App\Models\Evento;
use App\Models\Miembro;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use App\Models\Banda;

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
            ->whereDate('eventos.fecha', '>=', $miembro->created_at->toDateString())
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
            if (in_array($record->estado, ['PUNTUAL', 'RETRASO', 'PRESENTE'])) {
                $streak++;
            } else if ($record->estado == 'FALTA' || is_null($record->estado)) {
                break;
            }
            if (!in_array($record->estado, ['PUNTUAL', 'RETRASO', 'PRESENTE', 'JUSTIFICADO'])) {
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
        $data = $this->getReportData($request);
        return response()->json($data);
    }

    public function reportMatrix(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->toDateString());
        $idTipoEvento = $request->input('id_tipo_evento');
        $idSeccion = $request->input('id_seccion');
        $idInstrumento = $request->input('id_instrumento');

        $user = auth()->user();
        $miMiembro = $user?->miembro;
        $role = strtoupper($miMiembro?->rol?->rol ?? '');
        $isJefe = str_contains($role, 'JEFE') || str_contains($role, 'DELEGADO');
        $isAdminOrDirector = in_array($role, ['ADMIN', 'DIRECTOR', 'ADMINISTRADOR']);

        // 1. Get Events in range
        $eventQuery = Evento::whereBetween('fecha', [$startDate, $endDate])
            ->where('id_banda', $user->id_banda ?? 0)
            ->with('tipo');

        if ($idTipoEvento) {
            $types = is_array($idTipoEvento) ? $idTipoEvento : explode(',', $idTipoEvento);
            $eventQuery->whereIn('id_tipo_evento', $types);
        }

        $eventos = $eventQuery->orderBy('fecha')->orderBy('hora')->get();
        $eventIds = $eventos->pluck('id_evento');

        // 2. Get Members
        $memberQuery = Miembro::with(['instrumento', 'seccion'])
            ->where('id_banda', $user->id_banda ?? 0);

        if ($isJefe && !$isAdminOrDirector && $miMiembro && $miMiembro->id_instrumento) {
            $memberQuery->where('id_instrumento', $miMiembro->id_instrumento);
        }

        if ($idSeccion) {
            $memberQuery->where('id_seccion', $idSeccion);
        }

        if ($idInstrumento) {
            $memberQuery->where('id_instrumento', $idInstrumento);
        }

        $miembros = $memberQuery->get();
        $memberIds = $miembros->pluck('id_miembro');

        // 3. Get Assistances
        $assistances = DB::table('asistencias')
            ->join('convocatoria_evento', 'asistencias.id_convocatoria', '=', 'convocatoria_evento.id_convocatoria')
            ->whereIn('convocatoria_evento.id_evento', $eventIds)
            ->whereIn('convocatoria_evento.id_miembro', $memberIds)
            ->select('convocatoria_evento.id_miembro', 'convocatoria_evento.id_evento', 'asistencias.estado', 'asistencias.minutos_retraso')
            ->get();

        // 4. Map Assistances to a grid
        $grid = [];
        foreach ($assistances as $asist) {
            $grid[$asist->id_miembro][$asist->id_evento] = [
                'estado' => $asist->estado,
                'retraso' => $asist->minutos_retraso
            ];
        }

        // Group members by instrument if Director/Admin
        $groupedReport = [];
        if ($isAdminOrDirector) {
            $orderMap = [
                'PLATILLO' => 1, 'TAMBOR' => 2, 'TIMBAL' => 3, 'BOMBO' => 4,
                'TROMBON' => 5, 'CLARINETE' => 6, 'BARITONO' => 7, 'TROMPETA' => 8, 'HELICON' => 9
            ];

            $groupedMiembros = $miembros->groupBy('instrumento.instrumento');

            // Sort keys based on the map
            $sortedKeys = $groupedMiembros->keys()->sortBy(function($key) use ($orderMap) {
                return $orderMap[strtoupper($key)] ?? 99;
            });

            foreach ($sortedKeys as $instrument) {
                $mems = $groupedMiembros[$instrument];
                $groupedReport[] = [
                    'instrumento' => $instrument ?: 'Sin Instrumento',
                    'miembros' => $mems->map(function($m) use ($grid) {
                        return [
                            'id_miembro' => $m->id_miembro,
                            'nombre_completo' => "{$m->nombres} {$m->apellidos}",
                            'nombres' => $m->nombres,
                            'apellidos' => $m->apellidos,
                            'asistencias' => $grid[$m->id_miembro] ?? []
                        ];
                    })
                ];
            }
        } else {
            // Section Leader view (just one group or simple list)
            $groupedReport[] = [
                'instrumento' => $miembros->first()?->instrumento?->instrumento ?? 'Mi Sección',
                'miembros' => $miembros->map(function($m) use ($grid) {
                    return [
                        'id_miembro' => $m->id_miembro,
                        'nombre_completo' => "{$m->nombres} {$m->apellidos}",
                        'nombres' => $m->nombres,
                        'apellidos' => $m->apellidos,
                        'asistencias' => $grid[$m->id_miembro] ?? []
                    ];
                })
            ];
        }

        return response()->json([
            'eventos' => $eventos->map(fn($e) => [
                'id_evento' => $e->id_evento,
                'evento' => $e->evento,
                'fecha' => $e->fecha,
                'dia' => Carbon::parse($e->fecha)->format('d'),
                'tipo' => $e->tipo?->evento ?? 'N/A'
            ]),
            'data' => $groupedReport
        ]);
    }

    public function downloadGroupReportPdf(Request $request)
    {
        $data = $this->getReportData($request);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.group_attendance', $data);

        $filename = 'Reporte_Asistencia_' . Carbon::now()->format('Y-m-d') . '.pdf';
        return $pdf->download($filename);
    }

    private function getReportData(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfYear()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->toDateString());
        $idSeccion = $request->input('id_seccion');
        $idInstrumento = $request->input('id_instrumento');
        $idTipoEvento = $request->input('id_tipo_evento');

        $user = auth()->user();
        $miMiembro = $user?->miembro;
        $role = strtoupper($miMiembro?->rol?->rol ?? '');
        $isJefe = str_contains($role, 'JEFE') || str_contains($role, 'DELEGADO');
        $isAdminOrDirector = in_array($role, ['ADMIN', 'DIRECTOR', 'ADMINISTRADOR']);

        $banda = Banda::find($user->id_banda);
        $logoBase64 = null;

        if ($banda && $banda->logo) {
            $path = str_replace('/storage/', '', $banda->logo);
            if (Storage::disk('public')->exists($path)) {
                $file = Storage::disk('public')->get($path);
                $type = Storage::disk('public')->mimeType($path);
                $logoBase64 = 'data:' . $type . ';base64,' . base64_encode($file);
            }
        }

        $query = Miembro::with(['instrumento', 'seccion'])
            ->where('id_banda', $user->id_banda ?? 0);

        // AUTO-FILTER for Jefe de Sección
        if ($isJefe && !$isAdminOrDirector && $miMiembro && $miMiembro->id_instrumento) {
            $query->where('id_instrumento', $miMiembro->id_instrumento);
        }

        if ($idSeccion) {
            $query->where('id_seccion', $idSeccion);
        }

        if ($idInstrumento) {
            $query->where('id_instrumento', $idInstrumento);
        }

        $miembros = $query->get();

        $statsQuery = DB::table('convocatoria_evento')
            ->join('eventos', 'convocatoria_evento.id_evento', '=', 'eventos.id_evento')
            ->join('miembros', 'convocatoria_evento.id_miembro', '=', 'miembros.id_miembro')
            ->leftJoin('asistencias', 'convocatoria_evento.id_convocatoria', '=', 'asistencias.id_convocatoria')
            ->whereBetween('eventos.fecha', [$startDate, $endDate])
            ->whereRaw('eventos.fecha >= CAST(miembros.created_at AS DATE)');

        if ($idTipoEvento) {
            $types = is_array($idTipoEvento) ? $idTipoEvento : explode(',', $idTipoEvento);
            $statsQuery->whereIn('eventos.id_tipo_evento', $types);
        }

        $stats = $statsQuery->select(
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
            $s = $stats->get($m->id_miembro) ?? (object)[
                'total_events' => 0,
                'present_count' => 0,
                'absent_count' => 0,
                'justified_count' => 0,
                'unmarked_count' => 0
            ];

            $rate = $s->total_events > 0 ? round(($s->present_count / $s->total_events) * 100) : 0;
            // Calculate streak just for the report table if needed, or omit.
            // Reuse logic if necessary, but for now we keep it simple.

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
                'rate' => $rate,
                // Adding a mocked streak here for compatibility with ReportesHome if it expects it in the future,
                // but real streak calculation is expensive. Let's stick to the rate being the ranking metric.
                'streak' => $rate // Using rate as a proxy for "score" in simple views
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

        return [
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
                'id_seccion' => $idSeccion,
                'id_tipo_evento' => $idTipoEvento
            ],
            'banda' => [
                'nombre' => $banda?->nombre ?? 'Monster Band',
                'logo' => $logoBase64
            ]
        ];
    }

    public function getRankings(Request $request)
    {
        $user = auth()->user();
        $miMiembro = $user?->miembro;
        $role = strtoupper($miMiembro?->rol?->rol ?? '');
        $isJefe = str_contains($role, 'JEFE') || str_contains($role, 'DELEGADO');

        // Get members
        $query = Miembro::with('instrumento');

        if ($isJefe && $miMiembro && $miMiembro->id_instrumento) {
            $query->where('id_instrumento', $miMiembro->id_instrumento);
        }

        $miembros = $query->get();
        if ($miembros->isEmpty()) return response()->json(['rankings' => []]);

        // Pre-fetch all past assistances for these members
        $allAsistencias = DB::table('convocatoria_evento')
            ->join('eventos', 'convocatoria_evento.id_evento', '=', 'eventos.id_evento')
            ->join('miembros', 'convocatoria_evento.id_miembro', '=', 'miembros.id_miembro')
            ->leftJoin('asistencias', 'convocatoria_evento.id_convocatoria', '=', 'asistencias.id_convocatoria')
            ->where('eventos.fecha', '<', Carbon::today()->toDateString())
            ->where('eventos.id_banda', auth()->user()->id_banda ?? 0)
            ->whereRaw('eventos.fecha >= CAST(miembros.created_at AS DATE)')
            ->select('convocatoria_evento.id_miembro', 'asistencias.estado', 'eventos.fecha')
            ->orderBy('eventos.fecha', 'desc')
            ->get()
            ->groupBy('id_miembro');

        $rankings = [];

        foreach ($miembros as $miembro) {
            $records = $allAsistencias->get($miembro->id_miembro, collect());
            $streak = 0;

            foreach ($records as $rec) {
                if (in_array($rec->estado, ['PUNTUAL', 'RETRASO'])) {
                    $streak++;
                } else if ($rec->estado === 'FALTA' || is_null($rec->estado)) {
                    // Stop streak on absence
                    break;
                }
            }

            if ($streak > 0) {
                $rankings[] = [
                    'id_miembro' => $miembro->id_miembro,
                    'nombres' => $miembro->nombres,
                    'apellidos' => $miembro->apellidos,
                    'instrumento' => $miembro->instrumento?->instrumento ?? 'N/A',
                    'streak' => $streak
                ];
            }
        }

        usort($rankings, fn($a, $b) => $b['streak'] - $a['streak']);

        return response()->json(['rankings' => $rankings]);
    }
}

