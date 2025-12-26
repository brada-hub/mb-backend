<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\EventoCupo;
use App\Models\Asistencia;
use App\Models\Notificacion;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EventoController extends Controller
{
    /**
     * ═══════════════════════════════════════════════════════════
     * LISTAR EVENTOS
     * ═══════════════════════════════════════════════════════════
     */
    public function index(Request $request): JsonResponse
    {
        $query = Evento::with(['creador', 'cupos.seccion'])
            ->activos();

        // Filtrar por tipo
        if ($request->has('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        // Filtrar por estado
        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        // Filtrar por fecha
        if ($request->has('fecha_desde')) {
            $query->where('fecha', '>=', $request->fecha_desde);
        }

        if ($request->has('fecha_hasta')) {
            $query->where('fecha', '<=', $request->fecha_hasta);
        }

        // Ordenar
        if ($request->get('proximos', false)) {
            $query->proximos();
        } else {
            $query->orderByDesc('fecha');
        }

        $eventos = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $eventos,
        ]);
    }

    /**
     * ═══════════════════════════════════════════════════════════
     * VER EVENTO
     * ═══════════════════════════════════════════════════════════
     */
    public function show(Evento $evento): JsonResponse
    {
        $evento->load([
            'creador',
            'cupos.seccion',
            'miembros.seccion',
            'asistencias.miembro',
        ]);

        return response()->json([
            'success' => true,
            'data' => $evento,
        ]);
    }

    /**
     * ═══════════════════════════════════════════════════════════
     * CREAR EVENTO
     * ═══════════════════════════════════════════════════════════
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nombre' => 'required|string|max:200',
            'tipo' => 'required|in:ensayo,contrato',
            'descripcion' => 'nullable|string',
            'fecha' => 'required|date',
            'hora_citacion' => 'required|date_format:H:i',
            'lugar' => 'nullable|string|max:200',
            'direccion' => 'nullable|string',
            'latitud' => 'required|numeric',
            'longitud' => 'required|numeric',
            'radio_geofence' => 'nullable|integer|min:10|max:500',
            'tolerancia_minutos' => 'nullable|integer|min:0|max:120',
            'descuento_por_minuto' => 'nullable|numeric|min:0',
            'monto_total' => 'nullable|numeric|min:0',
            'cliente' => 'nullable|string|max:200',
            'cliente_celular' => 'nullable|string|max:20',
            'cupos' => 'nullable|array',
            'cupos.*.seccion_id' => 'required_with:cupos|exists:secciones,id',
            'cupos.*.cantidad' => 'required_with:cupos|integer|min:0',
        ]);

        $evento = Evento::create([
            ...$request->except('cupos'),
            'creado_por' => $request->user()->miembro ? $request->user()->miembro->id : null,
            'estado' => Evento::ESTADO_BORRADOR,
        ]);

        // Crear cupos por sección
        if ($request->has('cupos')) {
            foreach ($request->cupos as $cupo) {
                EventoCupo::create([
                    'evento_id' => $evento->id,
                    'seccion_id' => $cupo['seccion_id'],
                    'cantidad' => $cupo['cantidad'],
                ]);
            }
        }

        $evento->load(['cupos.seccion']);

        return response()->json([
            'success' => true,
            'message' => 'Evento creado correctamente',
            'data' => $evento,
        ], 201);
    }

    /**
     * ═══════════════════════════════════════════════════════════
     * ACTUALIZAR EVENTO
     * ═══════════════════════════════════════════════════════════
     */
    public function update(Request $request, Evento $evento): JsonResponse
    {
        $request->validate([
            'nombre' => 'sometimes|string|max:200',
            'descripcion' => 'nullable|string',
            'fecha' => 'sometimes|date',
            'hora_citacion' => 'sometimes|date_format:H:i',
            'lugar' => 'nullable|string|max:200',
            'direccion' => 'nullable|string',
            'latitud' => 'sometimes|numeric',
            'longitud' => 'sometimes|numeric',
            'radio_geofence' => 'nullable|integer|min:10|max:500',
            'tolerancia_minutos' => 'nullable|integer|min:0|max:120',
            'descuento_por_minuto' => 'nullable|numeric|min:0',
            'monto_total' => 'nullable|numeric|min:0',
            'cliente' => 'nullable|string|max:200',
            'cliente_celular' => 'nullable|string|max:20',
            'estado' => 'sometimes|in:borrador,confirmado,en_curso,finalizado,cancelado',
        ]);

        $evento->update($request->except('cupos'));

        // Actualizar cupos si se envían
        if ($request->has('cupos')) {
            // Eliminar cupos existentes
            $evento->cupos()->delete();

            // Crear nuevos cupos
            foreach ($request->cupos as $cupo) {
                EventoCupo::create([
                    'evento_id' => $evento->id,
                    'seccion_id' => $cupo['seccion_id'],
                    'cantidad' => $cupo['cantidad'],
                ]);
            }
        }

        $evento->load(['cupos.seccion']);

        return response()->json([
            'success' => true,
            'message' => 'Evento actualizado correctamente',
            'data' => $evento,
        ]);
    }

    /**
     * ═══════════════════════════════════════════════════════════
     * ELIMINAR EVENTO
     * ═══════════════════════════════════════════════════════════
     */
    public function destroy(Evento $evento): JsonResponse
    {
        $evento->delete();

        return response()->json([
            'success' => true,
            'message' => 'Evento eliminado correctamente',
        ]);
    }

    /**
     * ═══════════════════════════════════════════════════════════
     * AGREGAR MIEMBRO A LA LISTA
     * ═══════════════════════════════════════════════════════════
     */
    public function agregarMiembro(Request $request, Evento $evento): JsonResponse
    {
        $request->validate([
            'miembro_id' => 'required|exists:miembros,id',
            'seccion_id' => 'required|exists:secciones,id',
        ]);

        // Verificar que no esté ya en la lista
        if ($evento->miembros()->where('miembro_id', $request->miembro_id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'El miembro ya está en la lista',
            ], 422);
        }

        // Agregar miembro
        $evento->miembros()->attach($request->miembro_id, [
            'seccion_id' => $request->seccion_id,
            'estado' => 'propuesto',
            'propuesto_por' => $request->user()->miembro ? $request->user()->miembro->id : null,
        ]);

        // Crear registro de asistencia pendiente
        Asistencia::firstOrCreate([
            'evento_id' => $evento->id,
            'miembro_id' => $request->miembro_id,
        ], [
            'estado' => Asistencia::ESTADO_PENDIENTE,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Miembro agregado a la lista',
        ]);
    }

    /**
     * ═══════════════════════════════════════════════════════════
     * QUITAR MIEMBRO DE LA LISTA
     * ═══════════════════════════════════════════════════════════
     */
    public function quitarMiembro(Evento $evento, int $miembroId): JsonResponse
    {
        $evento->miembros()->detach($miembroId);

        // Eliminar registro de asistencia si existe
        Asistencia::where('evento_id', $evento->id)
            ->where('miembro_id', $miembroId)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Miembro removido de la lista',
        ]);
    }

    /**
     * ═══════════════════════════════════════════════════════════
     * CONFIRMAR LISTA
     * ═══════════════════════════════════════════════════════════
     */
    public function confirmarLista(Request $request, Evento $evento): JsonResponse
    {
        if ($evento->lista_confirmada) {
            return response()->json([
                'success' => false,
                'message' => 'La lista ya fue confirmada anteriormente',
            ], 422);
        }

        $evento->confirmarLista();

        // Notificar a todos los miembros confirmados
        $miembrosIds = $evento->miembros()
            ->wherePivot('estado', 'confirmado')
            ->pluck('miembros.id')
            ->toArray();

        Notificacion::enviarMasivo(
            $miembrosIds,
            Notificacion::TIPO_EVENTO,
            "📋 Has sido contado para: {$evento->nombre}",
            "Fecha: {$evento->fecha_formateada} a las {$evento->hora_citacion_formateada}. Lugar: {$evento->lugar}",
            ['evento_id' => $evento->id]
        );

        // Marcar como notificados
        $evento->miembros()->update([
            'evento_miembros.notificado' => true,
            'evento_miembros.fecha_notificacion' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Lista confirmada y miembros notificados',
            'data' => [
                'total_notificados' => count($miembrosIds),
            ],
        ]);
    }

    /**
     * ═══════════════════════════════════════════════════════════
     * OBTENER LISTA DE MIEMBROS DEL EVENTO
     * ═══════════════════════════════════════════════════════════
     */
    public function obtenerLista(Evento $evento): JsonResponse
    {
        $miembros = $evento->miembros()
            ->with(['seccion', 'categoria'])
            ->get()
            ->groupBy('pivot.seccion_id');

        $cupos = $evento->cupos()
            ->with('seccion')
            ->get()
            ->keyBy('seccion_id');

        $resultado = [];
        foreach ($cupos as $seccionId => $cupo) {
            $resultado[] = [
                'seccion' => $cupo->seccion,
                'cupo' => $cupo->cantidad,
                'asignados' => $miembros->get($seccionId, collect())->count(),
                'miembros' => $miembros->get($seccionId, collect()),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $resultado,
        ]);
    }

    /**
     * ═══════════════════════════════════════════════════════════
     * PRÓXIMOS EVENTOS DEL MIEMBRO
     * ═══════════════════════════════════════════════════════════
     */
    public function misEventos(Request $request): JsonResponse
    {
        $miembro = $request->user()->miembro;

        if (!$miembro) {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        $miembroId = $miembro->id;

        $eventos = Evento::with(['cupos.seccion'])
            ->whereHas('miembros', function ($q) use ($miembroId) {
                $q->where('miembro_id', $miembroId)
                    ->where('estado', 'confirmado');
            })
            ->proximos()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $eventos,
        ]);
    }
}
