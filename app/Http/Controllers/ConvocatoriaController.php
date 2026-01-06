<?php

namespace App\Http\Controllers;

use App\Models\ConvocatoriaEvento;
use App\Models\Miembro;
use App\Models\Evento;
use Illuminate\Http\Request;

class ConvocatoriaController extends Controller
{
    /**
     * Get convocatorias for a specific event
     */
    public function index(Request $request)
    {
        $id_evento = $request->query('id_evento');
        $query = ConvocatoriaEvento::with(['miembro.seccion', 'miembro.instrumento', 'asistencia']);

        if ($id_evento) {
            $query->where('id_evento', $id_evento);
        }

        return $query->get();
    }

    /**
     * Get members available to be nominated for a section
     */
    public function miembrosParaPostular(Request $request)
    {
        $id_evento = $request->query('id_evento');
        $id_seccion = $request->query('id_seccion');

        if (!$id_evento) {
            return response()->json(['error' => 'id_evento is required'], 400);
        }

        // Get details of the target event to check for schedule overlaps
        $eventoTarget = Evento::findOrFail($id_evento);

        // Get members who:
        // 1. Are NOT already in the convocatoria for this event
        // 2. Are NOT already convocated for another event at the same date AND hour
        $query = Miembro::with(['instrumento', 'seccion'])
            ->whereDoesntHave('convocatorias', function ($q) use ($id_evento) {
                $q->where('id_evento', $id_evento);
            })
            ->whereDoesntHave('convocatorias.evento', function ($q) use ($eventoTarget) {
                $q->where('fecha', $eventoTarget->fecha)
                  ->where('hora', $eventoTarget->hora);
            });

        // Filter by section only if provided
        if ($id_seccion) {
            $query->where('id_seccion', $id_seccion);
        }

        return $query->get();
    }

    /**
     * Section Leader nominates a member
     */
    public function postular(Request $request)
    {
        $validated = $request->validate([
            'id_evento' => 'required|exists:eventos,id_evento',
            'id_miembros' => 'required|array',
            'id_miembros.*' => 'exists:miembros,id_miembro'
        ]);

        $convocatorias = [];
        foreach ($request->id_miembros as $id_miembro) {
            $convocatorias[] = ConvocatoriaEvento::updateOrCreate(
                ['id_evento' => $request->id_evento, 'id_miembro' => $id_miembro],
                ['confirmado_por_director' => $request->confirmar ?? false]
            );
        }

        return response()->json($convocatorias);
    }

    /**
     * Director confirms a member
     */
    public function confirmar(Request $request)
    {
        $validated = $request->validate([
            'id_convocatoria' => 'required|exists:convocatoria_evento,id_convocatoria'
        ]);

        $convocatoria = ConvocatoriaEvento::findOrFail($request->id_convocatoria);
        $convocatoria->confirmado_por_director = true;
        $convocatoria->save();

        return response()->json($convocatoria);
    }

    /**
     * Director confirms multiple members
     */
    public function confirmarMasivo(Request $request)
    {
        $validated = $request->validate([
            'id_convocatorias' => 'required|array',
            'id_convocatorias.*' => 'exists:convocatoria_evento,id_convocatoria'
        ]);

        ConvocatoriaEvento::whereIn('id_convocatoria', $request->id_convocatorias)
            ->update(['confirmado_por_director' => true]);

        return response()->json(['message' => 'Members confirmed successfully']);
    }

    /**
     * Remove from convocatoria
     */
    public function destroy($id)
    {
        $convocatoria = ConvocatoriaEvento::findOrFail($id);
        $convocatoria->delete();
        return response()->json(null, 204);
    }
}
