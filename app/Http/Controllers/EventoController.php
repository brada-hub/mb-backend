<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\TipoEvento;
use App\Models\Miembro;
use App\Models\ConvocatoriaEvento;
use App\Models\RequerimientoInstrumento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        return TipoEvento::all();
    }

    public function storeTipo(Request $request) {
        $validated = $request->validate([
            'evento' => 'required|string|unique:tipos_evento,evento'
        ]);

        $tipo = TipoEvento::create([
            'evento' => mb_strtoupper($validated['evento'], 'UTF-8')
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
            'requerimientos' => 'nullable|array',
            'requerimientos.*.id_instrumento' => 'exists:instrumentos,id_instrumento',
            'requerimientos.*.cantidad_necesaria' => 'integer|min:1'
        ], $messages);

        return DB::transaction(function () use ($request) {
            $data = $request->all();
            $data['evento'] = mb_strtoupper($data['evento'], 'UTF-8');
            $data['estado'] = true;

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

            // Logic for ENSAYO: Auto-convoke everyone
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

            $validated = $request->validate([
                'id_tipo_evento' => 'required|exists:tipos_evento,id_tipo_evento',
                'evento' => 'required|string',
                'fecha' => 'required|date',
                'hora' => 'required',
                'radio' => 'required|integer',
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
        $evento->delete(); // Soft delete if implemented, or hard delete
        return response()->json(null, 204);
    }

}
