<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use Illuminate\Http\Request;

class EventoController extends Controller
{
    public function index() {
        return Evento::with('tipo')->orderBy('fecha', 'desc')->get();
    }

    public function getTipos() {
        return \App\Models\TipoEvento::all();
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
            'radio' => 'required|integer|min:10' // Metros
        ], $messages);

        $data = $request->all();
        $data['evento'] = mb_strtoupper($data['evento'], 'UTF-8');
        $data['estado'] = true;

        return Evento::create($data);
    }

    public function update(Request $request, string $id)
    {
        $evento = Evento::findOrFail($id);

        $validated = $request->validate([
            'id_tipo_evento' => 'required|exists:tipos_evento,id_tipo_evento',
            'evento' => 'required|string',
            'fecha' => 'required|date',
            'hora' => 'required',
            'radio' => 'required|integer'
        ]);

        $data = $request->all();
        $data['evento'] = mb_strtoupper($data['evento'], 'UTF-8');

        $evento->update($data);
        return response()->json($evento);
    }

    public function destroy(string $id)
    {
        $evento = Evento::findOrFail($id);
        $evento->delete(); // Soft delete if implemented, or hard delete
        return response()->json(null, 204);
    }

    public function show(string $id)
    {
        return Evento::with('tipo')->findOrFail($id);
    }
}
