<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Miembro;
use App\Models\Evento;
use App\Models\Asistencia;
use App\Models\Liquidacion;
use App\Models\Seccion;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * ═══════════════════════════════════════════════════════════
     * DASHBOARD GENERAL (Admin/Director) - OPTIMIZADO
     * ═══════════════════════════════════════════════════════════
     */
    public function general(): JsonResponse
    {
        // Cache de 60 segundos para el dashboard
        $cacheKey = 'dashboard_general';
        $cacheTTL = 60;

        $data = cache()->remember($cacheKey, $cacheTTL, function () {
            // Usar una sola query para obtener totales
            $totalMiembros = Miembro::whereHas('user', fn($q) => $q->where('activo', true))->count();

            // Miembros por sección de forma eficiente (una sola query)
            $miembrosPorSeccion = DB::table('secciones')
                ->select([
                    'secciones.id',
                    'secciones.nombre',
                    'secciones.color',
                    'secciones.icono',
                    DB::raw('COALESCE(COUNT(DISTINCT miembros.id), 0) as total')
                ])
                ->leftJoin('miembros', function ($join) {
                    $join->on('secciones.id', '=', 'miembros.seccion_id')
                        ->whereNull('miembros.deleted_at');
                })
                ->leftJoin('users', function ($join) {
                    $join->on('miembros.user_id', '=', 'users.id')
                        ->where('users.activo', true);
                })
                ->where('secciones.activo', true)
                ->groupBy('secciones.id', 'secciones.nombre', 'secciones.color', 'secciones.icono', 'secciones.orden')
                ->orderBy('secciones.orden')
                ->get();

            // Próximos eventos (query simple con límite)
            $proximosEventos = Evento::where('fecha', '>=', now()->toDateString())
                ->where('estado', '!=', 'cancelado')
                ->orderBy('fecha')
                ->orderBy('hora_citacion')
                ->limit(5)
                ->get(['id', 'nombre', 'tipo', 'fecha', 'hora_citacion', 'lugar', 'estado'])
                ->map(function($evento) {
                    $evento->fecha_formateada = $evento->fecha ? $evento->fecha->format('d/m/Y') : '';
                    return $evento;
                });

            // Estadísticas del mes actual con una sola query
            $mesActual = now()->month;
            $añoActual = now()->year;

            $statsEventos = DB::table('eventos')
                ->select('tipo', DB::raw('count(*) as total'))
                ->whereMonth('fecha', $mesActual)
                ->whereYear('fecha', $añoActual)
                ->where('estado', '!=', 'cancelado')
                ->groupBy('tipo')
                ->pluck('total', 'tipo');

            // Estadísticas de asistencia del mes
            $estadisticasAsistencia = DB::table('asistencias')
                ->join('eventos', 'asistencias.evento_id', '=', 'eventos.id')
                ->select('asistencias.estado', DB::raw('count(*) as total'))
                ->whereMonth('eventos.fecha', $mesActual)
                ->whereYear('eventos.fecha', $añoActual)
                ->where('eventos.estado', '!=', 'cancelado')
                ->groupBy('asistencias.estado')
                ->pluck('total', 'estado');

            // Finanzas (query directa sin Eloquent)
            $finanzas = DB::table('liquidaciones')
                ->selectRaw('COALESCE(SUM(monto_final), 0) as total_pendiente, COUNT(DISTINCT miembro_id) as miembros_deuda')
                ->where('estado', '!=', 'pagado')
                ->first();

            return [
                'miembros' => [
                    'total' => (int) $totalMiembros,
                    'por_seccion' => $miembrosPorSeccion,
                ],
                'eventos' => [
                    'proximos' => $proximosEventos,
                    'mes_actual' => [
                        'ensayos' => (int) ($statsEventos['ensayo'] ?? 0),
                        'contratos' => (int) ($statsEventos['contrato'] ?? 0),
                        'total' => (int) $statsEventos->sum(),
                    ],
                ],
                'asistencia_mes' => [
                    'a_tiempo' => (int) ($estadisticasAsistencia['a_tiempo'] ?? 0),
                    'tarde' => (int) ($estadisticasAsistencia['tarde'] ?? 0),
                    'ausente' => (int) ($estadisticasAsistencia['ausente'] ?? 0),
                ],
                'finanzas' => [
                    'pagos_pendientes' => round((float) ($finanzas->total_pendiente ?? 0), 2),
                    'miembros_con_deuda' => (int) ($finanzas->miembros_deuda ?? 0),
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * ═══════════════════════════════════════════════════════════
     * DASHBOARD DEL MÚSICO
     * ═══════════════════════════════════════════════════════════
     */
    public function miembro(Request $request): JsonResponse
    {
        $miembro = $request->user();

        // Próximos eventos donde estoy contado
        $misProximosEventos = Evento::whereHas('miembros', function ($q) use ($miembro) {
            $q->where('miembro_id', $miembro->id)
                ->where('estado', 'confirmado');
        })
        ->proximos()
        ->limit(5)
        ->get(['id', 'nombre', 'tipo', 'fecha', 'hora_citacion', 'lugar']);

        // Mi asistencia del mes
        $mesActual = now()->month;
        $añoActual = now()->year;

        $misAsistencias = Asistencia::where('miembro_id', $miembro->id)
            ->whereHas('evento', function ($q) use ($mesActual, $añoActual) {
                $q->whereMonth('fecha', $mesActual)
                    ->whereYear('fecha', $añoActual);
            })
            ->get();

        // Mi saldo pendiente
        $miSaldo = Liquidacion::where('miembro_id', $miembro->id)
            ->where('estado', '!=', 'pagado')
            ->sum('monto_final');

        // Notificaciones no leídas
        $notificacionesNoLeidas = $miembro->notificaciones()
            ->noLeidas()
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'proximos_eventos' => $misProximosEventos,
                'asistencia_mes' => [
                    'a_tiempo' => $misAsistencias->where('estado', 'a_tiempo')->count(),
                    'tarde' => $misAsistencias->where('estado', 'tarde')->count(),
                    'ausente' => $misAsistencias->where('estado', 'ausente')->count(),
                    'total_eventos' => $misAsistencias->count(),
                ],
                'saldo_pendiente' => $miSaldo,
                'notificaciones_no_leidas' => $notificacionesNoLeidas,
            ],
        ]);
    }

    /**
     * ═══════════════════════════════════════════════════════════
     * ESTADÍSTICAS POR SECCIÓN
     * ═══════════════════════════════════════════════════════════
     */
    public function porSeccion(Request $request, Seccion $seccion): JsonResponse
    {
        $mesActual = now()->month;
        $añoActual = now()->year;

        // Miembros de la sección
        $miembros = Miembro::activos()
            ->where('seccion_id', $seccion->id)
            ->with('categoria')
            ->get();

        // Asistencia de la sección en el mes
        $asistencias = Asistencia::whereHas('miembro', function ($q) use ($seccion) {
            $q->where('seccion_id', $seccion->id);
        })
        ->whereHas('evento', function ($q) use ($mesActual, $añoActual) {
            $q->whereMonth('fecha', $mesActual)
                ->whereYear('fecha', $añoActual);
        })
        ->with('miembro')
        ->get();

        // Ranking de puntualidad
        $ranking = $miembros->map(function ($miembro) use ($asistencias) {
            $misAsistencias = $asistencias->where('miembro_id', $miembro->id);
            $total = $misAsistencias->count();
            $aTiempo = $misAsistencias->where('estado', 'a_tiempo')->count();

            return [
                'miembro' => [
                    'id' => $miembro->id,
                    'nombre_completo' => $miembro->nombre_completo,
                ],
                'total_eventos' => $total,
                'a_tiempo' => $aTiempo,
                'porcentaje' => $total > 0 ? round(($aTiempo / $total) * 100, 1) : 0,
            ];
        })->sortByDesc('porcentaje')->values();

        return response()->json([
            'success' => true,
            'data' => [
                'seccion' => $seccion,
                'total_miembros' => $miembros->count(),
                'miembros' => $miembros,
                'ranking_puntualidad' => $ranking,
            ],
        ]);
    }

    /**
     * ═══════════════════════════════════════════════════════════
     * MAPA DE MIEMBROS
     * ═══════════════════════════════════════════════════════════
     */
    public function mapaMiembros(): JsonResponse
    {
        $miembros = Miembro::activos()
            ->whereNotNull('latitud')
            ->whereNotNull('longitud')
            ->with('seccion')
            ->get(['id', 'nombres', 'apellidos', 'latitud', 'longitud', 'seccion_id', 'direccion']);

        return response()->json([
            'success' => true,
            'data' => $miembros->map(fn($m) => [
                'id' => $m->id,
                'nombre_completo' => $m->nombre_completo,
                'latitud' => $m->latitud,
                'longitud' => $m->longitud,
                'direccion' => $m->direccion,
                'seccion' => [
                    'nombre' => $m->seccion->nombre ?? null,
                    'color' => $m->seccion->color ?? '#6366f1',
                    'icono' => $m->seccion->icono ?? null,
                ],
            ]),
        ]);
    }
}
