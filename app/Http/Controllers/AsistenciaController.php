<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Asistencia;
use App\Models\Evento;
use App\Models\ConvocatoriaEvento;
use Carbon\Carbon;

class AsistenciaController extends Controller
{
    /**
     * Obtener eventos disponibles para pasar asistencia hoy
     * Solo muestra eventos del día actual
     */
    public function eventosHoy()
    {
        $hoy = Carbon::now('America/La_Paz')->toDateString();

        $eventos = Evento::with(['tipo', 'convocatorias' => function($q) {
                $q->where('confirmado_por_director', true)
                  ->with(['miembro.instrumento', 'asistencia']);
            }])
            ->whereDate('fecha', $hoy)
            ->orderBy('hora')
            ->get();

        // Agregar info de si el control de asistencia está habilitado
        $eventos = $eventos->map(function($evento) {
            $horaEvento = Carbon::parse($evento->fecha . ' ' . $evento->hora, 'America/La_Paz');
            $ahora = Carbon::now('America/La_Paz');

            // Permitir abrir asistencia 30 min antes de la hora y hasta 8 horas después
            $limiteInferior = $horaEvento->copy()->subMinutes(30);
            $limiteSuperior = $horaEvento->copy()->addHours(8);

            $puedeAbrir = $ahora->greaterThanOrEqualTo($limiteInferior) && $ahora->lessThanOrEqualTo($limiteSuperior);

            $evento->puede_marcar_asistencia = $puedeAbrir;
            $evento->minutos_para_inicio = $horaEvento->diffInMinutes($ahora, false);
            $evento->hora_servidor = $ahora->format('H:i:s');
            $evento->limite_inferior = $limiteInferior->format('H:i:s');

            return $evento;
        });

        return response()->json($eventos);
    }

    /**
     * Obtener lista de músicos para un evento con su estado de asistencia
     */
    public function listaAsistencia($id_evento)
    {
        $evento = Evento::with(['tipo', 'requerimientos.instrumento'])->findOrFail($id_evento);

        $convocatorias = ConvocatoriaEvento::where('id_evento', $id_evento)
            ->where('confirmado_por_director', true)
            ->with(['miembro.instrumento', 'miembro.seccion', 'asistencia'])
            ->get();

        // Calcular hora del evento para referencia
        $horaEvento = Carbon::parse($evento->fecha . ' ' . $evento->hora, 'America/La_Paz');
        $ahora = Carbon::now('America/La_Paz');

        // Estado del control - más estricto
        $limiteInferior = $horaEvento->copy()->subMinutes(30);
        $limiteSuperior = $horaEvento->copy()->addHours(8);

        $puedeMarcar = $ahora->greaterThanOrEqualTo($limiteInferior) && $ahora->lessThanOrEqualTo($limiteSuperior);

        // Obtener instrumentos únicos de los convocados para filtrado
        $instrumentos = $convocatorias->map(function($c) {
            return $c->miembro?->instrumento;
        })->filter()->unique('id_instrumento')->values();

        return response()->json([
            'evento' => $evento,
            'convocatorias' => $convocatorias,
            'instrumentos' => $instrumentos,
            'puede_marcar' => $puedeMarcar,
            'hora_evento' => $horaEvento->format('H:i'),
            'hora_actual' => $ahora->format('H:i'),
            'limite_inferior' => $limiteInferior->format('H:i')
        ]);
    }

    /**
     * Marcar asistencia individual (Admin/Director)
     */
    public function marcarManual(Request $request)
    {
        $request->validate([
            'id_convocatoria' => 'required|exists:convocatoria_evento,id_convocatoria',
            'estado' => 'required|in:PUNTUAL,RETRASO,FALTA,JUSTIFICADO'
        ]);

        $convocatoria = ConvocatoriaEvento::with('evento')->findOrFail($request->id_convocatoria);
        $evento = $convocatoria->evento;

        // Verificar que es el día del evento
        $hoyStr = Carbon::now('America/La_Paz')->toDateString();
        if ($evento->fecha !== $hoyStr) {
            return response()->json(['message' => 'Solo puedes marcar asistencia el día del evento'], 403);
        }

        $horaEvento = Carbon::parse($evento->fecha . ' ' . $evento->hora, 'America/La_Paz');
        $ahora = Carbon::now('America/La_Paz');

        // Calcular minutos de retraso si aplica
        $minutosRetraso = 0;
        if ($request->estado === 'RETRASO') {
            $minutosRetraso = max(0, $ahora->diffInMinutes($horaEvento, false) * -1);
        }

        $asistencia = Asistencia::updateOrCreate(
            ['id_convocatoria' => $request->id_convocatoria],
            [
                'hora_llegada' => $ahora->toTimeString(),
                'minutos_retraso' => $minutosRetraso,
                'estado' => $request->estado,
                'fecha_sincronizacion' => now()
            ]
        );

        return response()->json($asistencia);
    }

    /**
     * Marcar asistencia masiva (todos los presentes de una vez)
     */
    public function marcarMasivo(Request $request)
    {
        $request->validate([
            'id_evento' => 'required|exists:eventos,id_evento',
            'asistencias' => 'required|array',
            'asistencias.*.id_convocatoria' => 'required|exists:convocatoria_evento,id_convocatoria',
            'asistencias.*.estado' => 'required|in:PUNTUAL,RETRASO,FALTA,JUSTIFICADO'
        ]);

        $evento = Evento::findOrFail($request->id_evento);

        // Verificar que es el día del evento
        $hoyStr = Carbon::now('America/La_Paz')->toDateString();
        if ($evento->fecha !== $hoyStr) {
            return response()->json(['message' => 'Solo puedes marcar asistencia el día del evento'], 403);
        }

        $horaEvento = Carbon::parse($evento->fecha . ' ' . $evento->hora, 'America/La_Paz');
        $ahora = Carbon::now('America/La_Paz');

        $registros = [];

        foreach ($request->asistencias as $data) {
            $minutosRetraso = 0;
            if ($data['estado'] === 'RETRASO') {
                $minutosRetraso = max(0, $ahora->diffInMinutes($horaEvento, false) * -1);
            }

            $asistencia = Asistencia::updateOrCreate(
                ['id_convocatoria' => $data['id_convocatoria']],
                [
                    'hora_llegada' => $data['estado'] !== 'FALTA' ? $ahora->toTimeString() : null,
                    'minutos_retraso' => $minutosRetraso,
                    'estado' => $data['estado'],
                    'fecha_sincronizacion' => now()
                ]
            );

            $registros[] = $asistencia;
        }

        return response()->json([
            'message' => 'Asistencia registrada correctamente',
            'registros' => count($registros)
        ]);
    }

    /**
     * Reporte de asistencia de un evento
     */
    public function reporte($id_evento)
    {
        $evento = Evento::with('tipo')->findOrFail($id_evento);

        $convocatorias = ConvocatoriaEvento::where('id_evento', $id_evento)
            ->where('confirmado_por_director', true)
            ->with(['miembro.instrumento', 'miembro.seccion', 'asistencia'])
            ->get();

        $stats = [
            'total' => $convocatorias->count(),
            'puntuales' => $convocatorias->filter(fn($c) => $c->asistencia?->estado === 'PUNTUAL')->count(),
            'retrasos' => $convocatorias->filter(fn($c) => $c->asistencia?->estado === 'RETRASO')->count(),
            'faltas' => $convocatorias->filter(fn($c) => $c->asistencia?->estado === 'FALTA' || !$c->asistencia)->count(),
            'justificados' => $convocatorias->filter(fn($c) => $c->asistencia?->estado === 'JUSTIFICADO')->count(),
        ];

        return response()->json([
            'evento' => $evento,
            'convocatorias' => $convocatorias,
            'estadisticas' => $stats
        ]);
    }

    /**
     * Marcar asistencia propia (para músicos desde la app móvil)
     */
    public function marcar(Request $request)
    {
        $request->validate([
            'id_evento' => 'required|exists:eventos,id_evento',
            'latitud' => 'required',
            'longitud' => 'required'
        ]);

        $user = $request->user();
        $miembro = $user->miembro;

        if (!$miembro) return response()->json(['message' => 'Usuario no es miembro'], 403);

        // Find Convocatoria
        $convocatoria = ConvocatoriaEvento::where('id_evento', $request->id_evento)
            ->where('id_miembro', $miembro->id_miembro)
            ->first();

        if (!$convocatoria) {
            return response()->json(['message' => 'No estás convocado a este evento'], 403);
        }

        if (!$convocatoria->confirmado_por_director) {
            return response()->json(['message' => 'Tu convocatoria no ha sido confirmada todavía'], 403);
        }

        $evento = Evento::find($request->id_evento);

        $now = Carbon::now();
        $horaEvento = Carbon::parse($evento->fecha . ' ' . $evento->hora);
        $diff = $now->diffInMinutes($horaEvento, false);

        $asistencia = Asistencia::updateOrCreate(
            ['id_convocatoria' => $convocatoria->id_convocatoria],
            [
                'hora_llegada' => $now->toTimeString(),
                'minutos_retraso' => $diff < 0 ? abs($diff) : 0,
                'estado' => $diff < -15 ? 'RETRASO' : 'PUNTUAL',
                'latitud_marcado' => $request->latitud,
                'longitud_marcado' => $request->longitud,
                'fecha_sincronizacion' => now()
            ]
        );

        return response()->json($asistencia);
    }

    public function syncOffline(Request $request)
    {
        $request->validate([
            'asistencias' => 'required|array'
        ]);

        $results = [];
        $miembro = $request->user()->miembro;

        foreach ($request->asistencias as $data) {
            // Check duplicate by UUID
            if (Asistencia::where('offline_uuid', $data['offline_uuid'])->exists()) {
                continue;
            }

            // Find Convocatoria
            $convocatoria = ConvocatoriaEvento::where('id_evento', $data['id_evento'])
                ->where('id_miembro', $miembro->id_miembro)
                ->first();

            if (!$convocatoria) continue;

            // Create record
            $asistencia = Asistencia::create([
                'id_convocatoria' => $convocatoria->id_convocatoria,
                'hora_llegada' => $data['hora_llegada'],
                'minutos_retraso' => $data['minutos_retraso'],
                'estado' => $data['estado'],
                'offline_uuid' => $data['offline_uuid'],
                'latitud_marcado' => $data['latitud'],
                'longitud_marcado' => $data['longitud'],
                'fecha_sincronizacion' => now()
            ]);

            $results[] = $asistencia->id_asistencia;
        }

        return response()->json(['synced_ids' => $results]);
    }
}
