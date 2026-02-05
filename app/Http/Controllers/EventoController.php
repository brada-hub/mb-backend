<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\TipoEvento;
use App\Models\Miembro;
use App\Models\ConvocatoriaEvento;
use App\Models\RequerimientoInstrumento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EventoController extends Controller
{
    public function index() {
        $user = auth()->user();

        // Cargar relación miembro si hace falta
        if ($user && !$user->relationLoaded('miembro')) {
            $user->load('miembro');
        }

        // 1. Obtener TODOS los eventos (Agenda Pública para toda la banda)
        $eventos = Evento::with('tipo')
                        ->orderBy('fecha', 'desc')
                        ->orderBy('hora', 'asc')
                        ->get();

        // 2. Si es un usuario autenticado con miembro asociado, marcamos cuáles le tocan
        if ($user && $user->miembro) {
            // Obtenemos los IDs de eventos donde el miembro está convocado
            $misEventosIds = \App\Models\ConvocatoriaEvento::where('id_miembro', $user->miembro->id_miembro)
                                ->pluck('id_evento')
                                ->toArray();

            $eventos->transform(function($evento) use ($misEventosIds) {
                // Forzar booleano para el frontend
                $evento->estoy_convocado = in_array($evento->id_evento, $misEventosIds);
                return $evento;
            });
        }

        return $eventos;
    }

    public function getTipos() {
        return TipoEvento::withoutGlobalScope('banda')
            ->where(function($q) {
                $q->where('id_banda', auth()->user()->id_banda)
                  ->orWhereNull('id_banda');
            })
            ->get();
    }

    public function storeTipo(Request $request) {
        $validated = $request->validate([
            'evento' => 'required|string|unique:tipos_evento,evento',
            'minutos_antes_marcar' => 'nullable|integer|min:0',
            'horas_despues_sellar' => 'nullable|integer|min:0',
            'minutos_tolerancia' => 'nullable|integer|min:0',
            'minutos_cierre' => 'nullable|integer|min:0'
        ]);

        $tipo = TipoEvento::create([
            'evento' => mb_strtoupper($validated['evento'], 'UTF-8'),
            'minutos_antes_marcar' => $validated['minutos_antes_marcar'] ?? 30,
            'horas_despues_sellar' => $validated['horas_despues_sellar'] ?? 24,
            'minutos_tolerancia' => $validated['minutos_tolerancia'] ?? 15,
            'minutos_cierre' => $validated['minutos_cierre'] ?? 60
        ]);

        return response()->json($tipo, 201);
    }

    public function proximos() {
        return Evento::with('tipo')
                     ->where('fecha', '>=', now()->toDateString())
                     ->where('estado', true)
                     ->orderBy('fecha', 'asc')
                     ->orderBy('hora', 'asc')
                     ->get();
    }

    public function proximasConvocatorias() {
        $user = auth()->user();
        $miembro = $user->miembro;
        $esJefe = $miembro && $miembro->rol?->rol === 'JEFE DE SECCIÓN';
        $miInstrumentoId = $miembro?->id_instrumento;

        $eventos = Evento::with(['tipo', 'requerimientos.instrumento', 'convocatorias.miembro'])
            ->where('fecha', '>=', now()->toDateString())
            ->where('estado', true)
            ->whereHas('requerimientos')
            ->orderBy('fecha', 'asc')
            ->get();

        // Calcular resumen para el frontend
        $eventos->map(function($ev) use ($esJefe, $miInstrumentoId) {
            $totalNecesario = $ev->requerimientos->sum('cantidad_necesaria');
            $totalConvocado = $ev->convocatorias->count();

            $ev->meta_formacion = [
                'total_necesario' => $totalNecesario,
                'total_convocado' => $totalConvocado,
                'completado' => $totalNecesario > 0 ? ($totalConvocado >= $totalNecesario) : true,
                'porcentaje' => $totalNecesario > 0 ? round(($totalConvocado / $totalNecesario) * 100) : 100
            ];

            // Si es Jefe de Sección, añadir su estado específico
            if ($esJefe && $miInstrumentoId) {
                $reqSeccion = $ev->requerimientos->where('id_instrumento', $miInstrumentoId)->first();
                if ($reqSeccion) {
                    $convocadosSeccion = $ev->convocatorias->filter(function($c) use ($miInstrumentoId) {
                        return $c->miembro?->id_instrumento == $miInstrumentoId;
                    })->count();

                    $ev->mi_seccion_status = [
                        'instrumento' => $reqSeccion->instrumento?->instrumento,
                        'total' => $reqSeccion->cantidad_necesaria,
                        'convocados' => $convocadosSeccion,
                        'completado' => $convocadosSeccion >= $reqSeccion->cantidad_necesaria
                    ];
                }
            }

            unset($ev->convocatorias); // Reducir payload
            return $ev;
        });

        return $eventos;
    }

    public function store(Request $request) {
        $messages = [
            'id_tipo_evento.required' => 'Debes seleccionar un tipo de evento',
            'fecha.required' => 'La fecha es obligatoria',
            'hora.required' => 'La hora es obligatoria',
            'latitud.required' => 'La ubicación GPS es requerida'
        ];

        $validated = $request->validate([
            'id_tipo_evento' => 'required|exists:tipos_evento,id_tipo_evento',
            'evento' => 'required|string',
            'fecha' => 'required|date',
            'hora' => 'required',
            'latitud' => 'nullable|numeric',
            'longitud' => 'nullable|numeric',
            'direccion' => 'nullable|string',
            'radio' => 'required|integer|min:10', // Metros
            'minutos_tolerancia' => 'nullable|integer|min:0',
            'minutos_cierre' => 'nullable|integer|min:0',
            'remunerado' => 'nullable|boolean',
            'monto_sugerido' => 'nullable|numeric|min:0',
            'requerimientos' => 'nullable|array',
            'requerimientos.*.id_instrumento' => 'exists:instrumentos,id_instrumento',
            'requerimientos.*.cantidad_necesaria' => 'integer|min:1'
        ], $messages);

        return DB::transaction(function () use ($request) {
            $data = $request->all();
            $data['evento'] = mb_strtoupper($data['evento'], 'UTF-8');
            $data['estado'] = true;

            // ENFORCEMENT DE PLAN (SaaS)
            $banda = auth()->user()->banda;
            if ($banda) {
                if (((isset($data['latitud']) && $data['latitud']) || (isset($data['longitud']) && $data['longitud'])) && !$banda->canUseGps()) {
                    return response()->json(['message' => 'Tu plan actual no incluye el control de asistencia por GPS.'], 403);
                }

                $esRemunerado = $data['remunerado'] ?? false;
                $canRemunerate = $banda->subscriptionPlan->can_upload_video ?? false; // Usamos video/PRO como proxy
                if ($esRemunerado && !$canRemunerate) {
                    return response()->json(['message' => 'La gestión de remuneraciones solo está disponible en el Plan Profesional.'], 403);
                }
            }

            $evento = Evento::create($data);

            // Logic for Requerimientos
            if ($request->has('requerimientos')) {
                foreach ($request->requerimientos as $req) {
                    RequerimientoInstrumento::create([
                        'id_evento' => $evento->id_evento,
                        'id_instrumento' => $req['id_instrumento'],
                        'cantidad_necesaria' => $req['cantidad_necesaria']
                    ]);
                }
            }

            // Logic for ENSAYO: Auto-convoke everyone (no instant notification)
            $tipo = TipoEvento::find($request->id_tipo_evento);
            if ($tipo && strtoupper($tipo->evento) === 'ENSAYO') {
                $miembros = Miembro::all();
                foreach ($miembros as $miembro) {
                    ConvocatoriaEvento::create([
                        'id_evento' => $evento->id_evento,
                        'id_miembro' => $miembro->id_miembro,
                        'confirmado_por_director' => true
                    ]);
                }
            }

            return $evento->load('tipo');
        });
    }

    public function show($id)
    {
        return Evento::with(['tipo', 'requerimientos.instrumento'])->findOrFail($id);
    }

    public function update(Request $request, string $id)
    {
        return DB::transaction(function () use ($request, $id) {
            $evento = Evento::findOrFail($id);
            $user = auth()->user();

            // Check if event is locked (historical record) - dynamic margin
            $tipo = $evento->tipo;
            $hrsDespues = $tipo->horas_despues_sellar ?? 24;
            $eventDateTime = Carbon::parse($evento->fecha . ' ' . $evento->hora);
            $isLocked = Carbon::now()->greaterThan($eventDateTime->addHours($hrsDespues));

            if ($isLocked) {
                // God Mode check
                $role = $user->miembro->rol->rol ?? '';
                if (strtoupper($role) !== 'ADMIN') {
                    return response()->json([
                        'message' => "Este evento ya es un registro histórico (más de {$hrsDespues}h) y está sellado para auditoría. Contacta a un Súper Admin si necesitas correcciones."
                    ], 403);
                }
            }

            $validated = $request->validate([
                'id_tipo_evento' => 'required|exists:tipos_evento,id_tipo_evento',
                'evento' => 'required|string',
                'fecha' => 'required|date',
                'hora' => 'required',
                'radio' => 'required|integer',
                'minutos_tolerancia' => 'nullable|integer|min:0',
                'minutos_cierre' => 'nullable|integer|min:0',
                'remunerado' => 'nullable|boolean',
                'monto_sugerido' => 'nullable|numeric|min:0',
                'requerimientos' => 'nullable|array', // Validar array
                'requerimientos.*.id_instrumento' => 'required|exists:instrumentos,id_instrumento',
                'requerimientos.*.cantidad_necesaria' => 'required|integer|min:1',
            ]);

            $data = $request->all();
            $data['evento'] = mb_strtoupper($data['evento'], 'UTF-8');
            // Validar latitud/longitud
            if (isset($data['latitud']) && !is_numeric($data['latitud'])) $data['latitud'] = null;
            if (isset($data['longitud']) && !is_numeric($data['longitud'])) $data['longitud'] = null;

            $evento->update($data);

            // Sync requerimientos
            if ($request->has('requerimientos')) {
                // Delete existing related to this event
                $evento->requerimientos()->delete();

                // Create new ones
                foreach ($request->requerimientos as $req) {
                    $evento->requerimientos()->create([
                        'id_instrumento' => $req['id_instrumento'],
                        'cantidad_necesaria' => $req['cantidad_necesaria']
                    ]);
                }
            }

            return response()->json($evento->load('tipo', 'requerimientos'));
        });
    }

    public function destroy(string $id)
    {
        $evento = Evento::findOrFail($id);
        $user = auth()->user();

        // Check lock for deletion
        $tipo = $evento->tipo;
        $hrsDespues = $tipo->horas_despues_sellar ?? 24;
        $eventDateTime = Carbon::parse($evento->fecha . ' ' . $evento->hora);
        if (Carbon::now()->greaterThan($eventDateTime->addHours($hrsDespues))) {
            $role = $user->miembro->rol->rol ?? '';
            if (strtoupper($role) !== 'ADMIN') {
                return response()->json([
                    'message' => "No se pueden eliminar registros históricos (más de {$hrsDespues}h) de la agenda para mantener la integridad de las estadísticas."
                ], 403);
            }
        }

        $evento->delete();
        return response()->json(null, 204);
    }

    /**
     * Notificar a los Jefes de Sección que deben armar sus listas
     */
    public function solicitarListas($id)
    {
        $evento = Evento::with(['requerimientos.instrumento', 'tipo'])->findOrFail($id);

        $instrumentosIds = $evento->requerimientos->pluck('id_instrumento')->unique();

        $jefes = \App\Models\Miembro::whereIn('id_instrumento', $instrumentosIds)
            ->whereHas('rol', function($q) {
                $q->where('rol', 'JEFE DE SECCIÓN');
            })
            ->with(['user', 'instrumento'])
            ->get();

        $enviados = 0;
        foreach ($jefes as $jefe) {
            if ($jefe->user) {
                \App\Models\Notificacion::enviar(
                    $jefe->user->id_user,
                    "Solicitud de Lista - {$evento->tipo->evento}",
                    "El Director solicita armar la lista de {$jefe->instrumento->instrumento} para el evento: {$evento->evento}.",
                    $evento->id_evento,
                    'convocatoria',
                    "/dashboard/eventos/{$evento->id_evento}/convocatoria"
                );
                $enviados++;
            }
        }

        return response()->json([
            'message' => "Solicitud enviada a {$enviados} Jefes de Sección.",
            'jefes_notificados' => $enviados
        ]);
    }
}
