<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Asistencia;
use App\Models\Evento;
use App\Models\ConvocatoriaEvento;
use Illuminate\Support\Str;
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
            // Fix: Asegurar formato de fecha para evitar errores de parseo con Carbon
            $fechaStr = ($evento->fecha instanceof Carbon) ? $evento->fecha->format('Y-m-d') : $evento->fecha;
            $horaEvento = Carbon::parse($fechaStr . ' ' . $evento->hora, 'America/La_Paz');
            $ahora = Carbon::now('America/La_Paz');

            $tipo = $evento->tipo;
            // Robustez: Valores por defecto si tipo es null
            $minAntes = $tipo ? ($tipo->minutos_antes_marcar ?? 15) : 15;
            $minCierre = $evento->minutos_cierre ?? ($tipo ? ($tipo->minutos_cierre ?? 60) : 60);
            $hrsSellar = $tipo ? ($tipo->horas_despues_sellar ?? 24) : 24;

            // Ventana para MÚSICOS (desde App)
            $limiteInferior = $horaEvento->copy()->subMinutes($minAntes);
            $limiteSuperiorMarca = $horaEvento->copy()->addMinutes($minCierre);

            // Ventana para AUDITORÍA (Sellar)
            $limiteSuperiorSello = $horaEvento->copy()->addHours($hrsSellar);

            $ahora = Carbon::now('America/La_Paz');
            $puedeMarcar = ($ahora->greaterThanOrEqualTo($limiteInferior) && $ahora->lessThanOrEqualTo($limiteSuperiorMarca)) && !$evento->asistencia_cerrada;
            $estaSellado = $ahora->greaterThan($limiteSuperiorSello);

            $evento->puede_marcar_asistencia = $puedeMarcar;
            $evento->esta_sellado = $estaSellado;
            $evento->minutos_para_inicio = $ahora->diffInMinutes($horaEvento, false);
            $evento->hora_servidor = $ahora->format('H:i:s');
            $evento->limite_inferior = $limiteInferior->format('H:i:s');
            $evento->limite_superior = $limiteSuperiorMarca->format('H:i:s');

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
        $fechaStr = ($evento->fecha instanceof Carbon) ? $evento->fecha->format('Y-m-d') : $evento->fecha;
        $horaEvento = Carbon::parse($fechaStr . ' ' . $evento->hora, 'America/La_Paz');
        $ahora = Carbon::now('America/La_Paz');

        $tipo = $evento->tipo;
        $minAntes = $tipo ? ($tipo->minutos_antes_marcar ?? 30) : 30;
        $minCierre = $evento->minutos_cierre ?? ($tipo ? ($tipo->minutos_cierre ?? 60) : 60);
        $hrsSellar = $tipo ? ($tipo->horas_despues_sellar ?? 24) : 24;

        // Límites
        $limiteInferior = $horaEvento->copy()->subMinutes($minAntes);
        $limiteSuperiorMarca = $horaEvento->copy()->addMinutes($minCierre);
        $limiteSuperiorSello = $horaEvento->copy()->addHours($hrsSellar);

        $puedeMarcar = ($ahora->greaterThanOrEqualTo($limiteInferior) && $ahora->lessThanOrEqualTo($limiteSuperiorMarca)) && !$evento->asistencia_cerrada;

        // Obtener instrumentos únicos de los convocados para filtrado
        $instrumentos = $convocatorias->map(function($c) {
            return $c->miembro?->instrumento;
        })->filter()->unique('id_instrumento')->values();

        return response()->json([
            'evento' => $evento,
            'convocatorias' => $convocatorias,
            'instrumentos' => $instrumentos,
            'puede_marcar' => $puedeMarcar,
            'asistencia_cerrada' => $evento->asistencia_cerrada
        ]);
    }

    /**
     * Cerrar manualmente la asistencia
     */
    public function cerrarAsistencia(Request $request)
    {
        $request->validate(['id_evento' => 'required|exists:eventos,id_evento']);

        $user = auth()->user();
        $role = strtoupper($user->miembro->rol->rol ?? '');

        if ($role !== 'ADMIN' && $role !== 'DIRECTOR') {
            return response()->json(['message' => 'Solo Admin/Director pueden cerrar asistencia.'], 403);
        }

        $evento = Evento::findOrFail($request->id_evento);

        // 1. Marcar automáticamente como FALTA a los que no tienen registro
        $convocatoriasSinAsistencia = ConvocatoriaEvento::where('id_evento', $evento->id_evento)
            ->where('confirmado_por_director', true)
            ->whereDoesntHave('asistencia')
            ->get();

        foreach ($convocatoriasSinAsistencia as $conv) {
            Asistencia::create([
                'id_convocatoria' => $conv->id_convocatoria,
                'estado' => 'FALTA',
                'observacion' => 'Cierre automático de asistencia',
                'fecha_sincronizacion' => now()
            ]);

            // Notificar al músico sobre su falta
            if ($conv->miembro && $conv->miembro->user) {
                \App\Models\Notificacion::enviar(
                    $conv->miembro->user->id_user,
                    "Registro de Inasistencia",
                    "Se ha registrado una FALTA en tu historial para el evento: {$evento->evento}.",
                    $evento->id_evento,
                    'asistencia',
                    '/dashboard/asistencia'
                );
            }
        }

        // 2. Cerrar el evento
        $evento->asistencia_cerrada = true;
        $evento->save();

        return response()->json(['message' => 'Asistencia cerrada. Los pendientes se marcaron como FALTA.', 'evento' => $evento]);
    }

    /**
     * Mandar recordatorios a los que no han marcado
     */
    public function enviarRecordatorios(Request $request)
    {
        $request->validate(['id_evento' => 'required|exists:eventos,id_evento']);
        $evento = Evento::findOrFail($request->id_evento);

        $convocatoriasPendientes = ConvocatoriaEvento::where('id_evento', $evento->id_evento)
            ->where('confirmado_por_director', true)
            ->whereDoesntHave('asistencia')
            ->with('miembro.user')
            ->get();

        $enviados = 0;
        foreach ($convocatoriasPendientes as $conv) {
            if ($conv->miembro && $conv->miembro->user) {
                $status = \App\Models\Notificacion::enviar(
                    $conv->miembro->user->id_user,
                    "¿Ya llegaste?",
                    "Aún no registras tu asistencia para: {$evento->evento}. ¡Hazlo antes de que cierre!",
                    $evento->id_evento,
                    'asistencia',
                    '/dashboard/asistencia'
                );
                if ($status) $enviados++;
            }
        }

        return response()->json([
            'message' => "Recordatorios enviados a {$enviados} músicos.",
            'enviados' => $enviados
        ]);
    }

    /**
     * Verifica si todos los convocados ya tienen asistencia y cierra el evento si es así.
     */
    private function checkAutoClose($id_evento)
    {
        $evento = Evento::find($id_evento);
        if (!$evento || $evento->asistencia_cerrada) return;

        $totalConvocados = ConvocatoriaEvento::where('id_evento', $id_evento)
            ->where('confirmado_por_director', true)
            ->count();

        $totalAsistencias = Asistencia::whereHas('convocatoria', function($q) use ($id_evento) {
            $q->where('id_evento', $id_evento);
        })->count();

        if ($totalConvocados > 0 && $totalAsistencias >= $totalConvocados) {
            $evento->asistencia_cerrada = true;
            $evento->save();
        }
    }

    /**
     * Marcar asistencia individual (Admin/Director)
     */
    public function marcarManual(Request $request)
    {
        $request->validate([
            'id_convocatoria' => 'required|exists:convocatoria_evento,id_convocatoria',
            'estado' => 'required|in:PRESENTE,FALTA,JUSTIFICADO',
            'observacion' => 'nullable|string'
        ]);

        $convocatoria = ConvocatoriaEvento::with('evento')->findOrFail($request->id_convocatoria);
        $evento = $convocatoria->evento;

        $fechaStr = ($evento->fecha instanceof Carbon) ? $evento->fecha->format('Y-m-d') : $evento->fecha;
        $horaEvento = Carbon::parse($fechaStr . ' ' . $evento->hora, 'America/La_Paz');
        $ahora = Carbon::now('America/La_Paz');

        // Registro Sellado tras N horas configuradas por tipo
        $tipo = $evento->tipo;
        $hrsDespues = $tipo ? ($tipo->horas_despues_sellar ?? 24) : 24;

        if ($ahora->greaterThan($horaEvento->copy()->addHours($hrsDespues))) {
            $role = auth()->user()->miembro->rol->rol ?? '';
            if (strtoupper($role) !== 'ADMIN') {
                return response()->json(['message' => 'Este registro ya está sellado por auditoría.'], 403);
            }
        }

        // --- RESTRICCIONES PARA JEFE DE SECCIÓN ---
        $user = auth()->user();
        $miMiembro = $user->miembro;
        $role = strtoupper($miMiembro->rol->rol ?? '');

        if (Str::contains($role, ['JEFE', 'DELEGADO'])) {
            // 1. Solo puede marcar a gente de su instrumento
            if ($convocatoria->miembro->id_instrumento !== $miMiembro->id_instrumento && $role !== 'ADMIN') {
                return response()->json(['message' => 'Solo puedes marcar asistencia a integrantes de tu instrumento.'], 403);
            }

            // 3. Jefes no pueden dar permisos (JUSTIFICADO)
            if ($request->estado === 'JUSTIFICADO' && $role !== 'ADMIN' && $role !== 'DIRECTOR') {
                 return response()->json(['message' => 'Solo el Director o Administrador pueden otorgar permisos (Justificados).'], 403);
            }
        }

        if (Str::contains($role, 'MÚSICO') && $role !== 'ADMIN' && $role !== 'DIRECTOR') {
            return response()->json(['message' => 'No tienes permisos para realizar marcados manuales.'], 403);
        }

        // 2. Protección GPS Universal: Ni director ni jefes pueden cambiar un marcado GPS legal, solo ADMIN
        $asistenciaExistente = Asistencia::where('id_convocatoria', $request->id_convocatoria)->first();
        if ($asistenciaExistente && $asistenciaExistente->latitud_marcado !== null && $role !== 'ADMIN') {
            return response()->json(['message' => 'No puedes modificar una asistencia registrada legítimamente vía GPS.'], 403);
        }

        $fechaStr = ($evento->fecha instanceof Carbon) ? $evento->fecha->format('Y-m-d') : $evento->fecha;
        $horaEvento = Carbon::parse($fechaStr . ' ' . $evento->hora, 'America/La_Paz');
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
                'observacion' => $request->observacion,
                'fecha_sincronizacion' => now()
            ]
        );

        $this->checkAutoClose($evento->id_evento);

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
            'asistencias.*.estado' => 'required|in:PRESENTE,FALTA,JUSTIFICADO'
        ]);

        $evento = Evento::findOrFail($request->id_evento);
        $fechaStr = ($evento->fecha instanceof Carbon) ? $evento->fecha->format('Y-m-d') : $evento->fecha;
        $horaEvento = Carbon::parse($fechaStr . ' ' . $evento->hora, 'America/La_Paz');
        $ahora = Carbon::now('America/La_Paz');

        // Registro Sellado tras N horas configuradas por tipo
        $tipo = $evento->tipo;
        $hrsDespues = $tipo ? ($tipo->horas_despues_sellar ?? 24) : 24;

        if ($ahora->greaterThan($horaEvento->copy()->addHours($hrsDespues))) {
            $role = auth()->user()->miembro->rol->rol ?? '';
            if (strtoupper($role) !== 'ADMIN') {
                return response()->json(['message' => 'El control masivo ya está sellado por auditoría.'], 403);
            }
        }

        $user = auth()->user();
        $miMiembro = $user->miembro;
        $miRole = strtoupper($miMiembro->rol->rol ?? '');

        if (Str::contains($miRole, 'MÚSICO') && $miRole !== 'ADMIN' && $miRole !== 'DIRECTOR') {
            return response()->json(['message' => 'No tienes permisos para realizar marcados masivos.'], 403);
        }

        $fechaStr = ($evento->fecha instanceof Carbon) ? $evento->fecha->format('Y-m-d') : $evento->fecha;
        $horaEvento = Carbon::parse($fechaStr . ' ' . $evento->hora, 'America/La_Paz');
        $ahora = Carbon::now('America/La_Paz');

        $registros = [];

        foreach ($request->asistencias as $data) {
            $conv = ConvocatoriaEvento::with('miembro')->find($data['id_convocatoria']);
            if (!$conv) continue;

            // Restricción GPS: Ni director ni jefes pueden sobreescribir un marcado GPS legal
            $existente = Asistencia::where('id_convocatoria', $data['id_convocatoria'])->first();
            if ($existente && $existente->latitud_marcado !== null && $miRole !== 'ADMIN') continue;

            // Restricción Jefe: Solo su instrumento
            if (($miRole === 'JEFE DE SECCION' || str_contains($miRole, 'JEFE')) && $miRole !== 'ADMIN') {
                if ($conv->miembro->id_instrumento !== $miMiembro->id_instrumento) continue;
            }

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

        $this->checkAutoClose($evento->id_evento);

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

        $evento = Evento::with('tipo')->find($request->id_evento);
        $tipo = $evento->tipo;

        $now = Carbon::now('America/La_Paz');
        $fechaStr = ($evento->fecha instanceof Carbon) ? $evento->fecha->format('Y-m-d') : $evento->fecha;
        $horaEvento = Carbon::parse($fechaStr . ' ' . $evento->hora, 'America/La_Paz');

        // --- VALIDACIÓN GEOGRÁFICA ---
        if ($evento->latitud && $evento->longitud) {
            $earthRadius = 6371000; // metros
            $latFrom = deg2rad($request->latitud);
            $lonFrom = deg2rad($request->longitud);
            $latTo = deg2rad($evento->latitud);
            $lonTo = deg2rad($evento->longitud);

            $latDelta = $latTo - $latFrom;
            $lonDelta = $lonTo - $lonFrom;

            $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
                cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));

            $distancia = $angle * $earthRadius;
            $radioMaximo = $evento->radio ?? 100;

            if ($distancia > $radioMaximo) {
                return response()->json([
                    'message' => 'Estás demasiado lejos del punto de encuentro.',
                    'distancia' => round($distancia, 2),
                    'radio_permitido' => $radioMaximo
                ], 403);
            }
        }

        // Reglas
        $minAntes = $tipo ? ($tipo->minutos_antes_marcar ?? 15) : 15;
        $minCierre = $evento->minutos_cierre ?? ($tipo ? ($tipo->minutos_cierre ?? 60) : 60);
        $minTolerancia = $evento->minutos_tolerancia ?? ($tipo ? ($tipo->minutos_tolerancia ?? 15) : 15);

        $limiteInferior = $horaEvento->copy()->subMinutes($minAntes);
        $limiteSuperior = $horaEvento->copy()->addMinutes($minCierre);

        if ($now->lessThan($limiteInferior)) {
            return response()->json(['message' => 'Todavía es muy pronto para marcar asistencia.'], 403);
        }

        if ($now->greaterThan($limiteSuperior)) {
            return response()->json(['message' => 'La asistencia para este evento ya ha cerrado.'], 403);
        }

        $diff = $now->diffInMinutes($horaEvento, false);

        $asistencia = Asistencia::updateOrCreate(
            ['id_convocatoria' => $convocatoria->id_convocatoria],
            [
                'hora_llegada' => $now->toTimeString(),
                'minutos_retraso' => $diff < 0 ? abs($diff) : 0,
                'estado' => 'PRESENTE',
                'latitud_marcado' => $request->latitud,
                'longitud_marcado' => $request->longitud,
                'fecha_sincronizacion' => now()
            ]
        );

        $this->checkAutoClose($evento->id_evento);

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
