<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asistencia;
use App\Models\Evento;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AsistenciaController extends Controller
{
    /**
     * ═══════════════════════════════════════════════════════════
     * REGISTRAR ASISTENCIA (Automático por GPS)
     * ═══════════════════════════════════════════════════════════
     */
    public function registrar(Request $request): JsonResponse
    {
        $request->validate([
            'evento_id' => 'required|exists:eventos,id',
            'latitud' => 'required|numeric',
            'longitud' => 'required|numeric',
        ]);

        $miembro = $request->user();
        $evento = Evento::findOrFail($request->evento_id);

        // Verificar que el miembro esté en la lista del evento
        if (!$evento->miembros()->where('miembro_id', $miembro->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No estás en la lista de este evento',
            ], 403);
        }

        // Verificar si ya tiene asistencia registrada
        $asistencia = Asistencia::where('evento_id', $evento->id)
            ->where('miembro_id', $miembro->id)
            ->first();

        if ($asistencia && $asistencia->estado !== Asistencia::ESTADO_PENDIENTE) {
            return response()->json([
                'success' => false,
                'message' => 'Tu asistencia ya fue registrada',
                'data' => $asistencia,
            ], 422);
        }

        // Verificar geolocalización
        if (!$evento->estaDentroDelRadio($request->latitud, $request->longitud)) {
            $distancia = $evento->calcularDistancia($request->latitud, $request->longitud);
            return response()->json([
                'success' => false,
                'message' => "No estás dentro del área del evento. Distancia: " . round($distancia) . "m",
                'data' => [
                    'distancia' => round($distancia),
                    'radio_permitido' => $evento->radio_geofence,
                ],
            ], 422);
        }

        // Registrar asistencia
        if (!$asistencia) {
            $asistencia = Asistencia::create([
                'evento_id' => $evento->id,
                'miembro_id' => $miembro->id,
                'estado' => Asistencia::ESTADO_PENDIENTE,
            ]);
        }

        $asistencia->registrarLlegada($request->latitud, $request->longitud);

        return response()->json([
            'success' => true,
            'message' => $asistencia->estado === Asistencia::ESTADO_A_TIEMPO
                ? '¡Asistencia registrada! Llegaste a tiempo'
                : "Asistencia registrada. Llegaste {$asistencia->minutos_retraso} minutos tarde",
            'data' => $asistencia,
        ]);
    }

    /**
     * ═══════════════════════════════════════════════════════════
     * REGISTRAR ASISTENCIA MANUAL (Director/Jefe)
     * ═══════════════════════════════════════════════════════════
     */
    public function registrarManual(Request $request): JsonResponse
    {
        $request->validate([
            'evento_id' => 'required|exists:eventos,id',
            'miembro_id' => 'required|exists:miembros,id',
            'estado' => 'required|in:a_tiempo,tarde,ausente,justificado',
            'minutos_retraso' => 'nullable|integer|min:0',
            'observaciones' => 'nullable|string',
            'justificacion' => 'nullable|string|required_if:estado,justificado',
        ]);

        $asistencia = Asistencia::firstOrCreate([
            'evento_id' => $request->evento_id,
            'miembro_id' => $request->miembro_id,
        ]);

        $data = [
            'estado' => $request->estado,
            'hora_llegada' => now(),
            'registro_manual' => true,
            'registrado_por' => $request->user()->id,
            'observaciones' => $request->observaciones,
        ];

        if ($request->estado === Asistencia::ESTADO_TARDE) {
            $data['minutos_retraso'] = $request->minutos_retraso ?? 0;

            $evento = Evento::find($request->evento_id);
            $data['descuento'] = $data['minutos_retraso'] * $evento->descuento_por_minuto;
        }

        if ($request->estado === Asistencia::ESTADO_JUSTIFICADO) {
            $data['justificacion'] = $request->justificacion;
            $data['justificado_por'] = $request->user()->id;
            $data['descuento'] = 0;
        }

        $asistencia->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Asistencia registrada correctamente',
            'data' => $asistencia,
        ]);
    }

    /**
     * ═══════════════════════════════════════════════════════════
     * ASISTENCIAS DE UN EVENTO
     * ═══════════════════════════════════════════════════════════
     */
    public function porEvento(Evento $evento): JsonResponse
    {
        $asistencias = Asistencia::with(['miembro.seccion'])
            ->where('evento_id', $evento->id)
            ->get()
            ->groupBy('estado');

        $resumen = [
            'total' => $evento->miembros()->wherePivot('estado', 'confirmado')->count(),
            'a_tiempo' => $asistencias->get(Asistencia::ESTADO_A_TIEMPO, collect())->count(),
            'tarde' => $asistencias->get(Asistencia::ESTADO_TARDE, collect())->count(),
            'ausente' => $asistencias->get(Asistencia::ESTADO_AUSENTE, collect())->count(),
            'justificado' => $asistencias->get(Asistencia::ESTADO_JUSTIFICADO, collect())->count(),
            'pendiente' => $asistencias->get(Asistencia::ESTADO_PENDIENTE, collect())->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'resumen' => $resumen,
                'asistencias' => Asistencia::with(['miembro.seccion', 'registrador'])
                    ->where('evento_id', $evento->id)
                    ->orderBy('hora_llegada')
                    ->get(),
            ],
        ]);
    }

    /**
     * ═══════════════════════════════════════════════════════════
     * HISTORIAL DE ASISTENCIAS DEL MIEMBRO
     * ═══════════════════════════════════════════════════════════
     */
    public function miHistorial(Request $request): JsonResponse
    {
        $miembro = $request->user();
        $meses = $request->get('meses', 3);

        $asistencias = Asistencia::with('evento')
            ->where('miembro_id', $miembro->id)
            ->whereHas('evento', function ($q) use ($meses) {
                $q->where('fecha', '>=', now()->subMonths($meses));
            })
            ->orderByDesc('created_at')
            ->get();

        $resumen = [
            'total' => $asistencias->count(),
            'a_tiempo' => $asistencias->where('estado', Asistencia::ESTADO_A_TIEMPO)->count(),
            'tarde' => $asistencias->where('estado', Asistencia::ESTADO_TARDE)->count(),
            'ausente' => $asistencias->where('estado', Asistencia::ESTADO_AUSENTE)->count(),
            'total_descuentos' => $asistencias->sum('descuento'),
            'total_minutos_retraso' => $asistencias->sum('minutos_retraso'),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'resumen' => $resumen,
                'asistencias' => $asistencias,
            ],
        ]);
    }

    /**
     * ═══════════════════════════════════════════════════════════
     * MARCAR AUSENTES (Al finalizar evento)
     * ═══════════════════════════════════════════════════════════
     */
    public function marcarAusentes(Evento $evento): JsonResponse
    {
        $pendientes = Asistencia::where('evento_id', $evento->id)
            ->where('estado', Asistencia::ESTADO_PENDIENTE)
            ->get();

        foreach ($pendientes as $asistencia) {
            $asistencia->marcarAusente('Marcado automáticamente al finalizar el evento');
        }

        return response()->json([
            'success' => true,
            'message' => "Se marcaron {$pendientes->count()} miembros como ausentes",
            'data' => [
                'total_ausentes' => $pendientes->count(),
            ],
        ]);
    }
}
