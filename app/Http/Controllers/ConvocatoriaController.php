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
            });

        // REGLA: Si el evento destino es de tipo "Estricto" (CONTRATO, BANDIN),
        // no mostrar miembros que ya estén en otro evento "Estricto" a la misma hora.
        // Se excluye ENSAYO de esta validación para permitir flexibilidad.
        $eventoTarget->load('tipo');
        $strictTypes = ['CONTRATO', 'BANDIN'];

        if (in_array($eventoTarget->tipo->evento, $strictTypes)) {
            $query->whereDoesntHave('convocatorias.evento', function ($q) use ($eventoTarget, $strictTypes) {
                $q->where('fecha', $eventoTarget->fecha)
                  ->where('hora', $eventoTarget->hora)
                  ->whereHas('tipo', function($t) use ($strictTypes) {
                      $t->whereIn('evento', $strictTypes);
                  });
            });
        }

        // Filter by section only if provided
        if ($id_seccion) {
            $query->where('id_seccion', $id_seccion);
        }

        if ($request->has('id_instrumento')) {
            $query->where('id_instrumento', $request->id_instrumento);
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
     * Reemplazar un miembro de la convocatoria (Solo Admin/Director)
     */
    public function reemplazar(Request $request)
    {
        $request->validate([
            'id_convocatoria' => 'required|exists:convocatoria_evento,id_convocatoria',
            'id_nuevo_miembro' => 'required|exists:miembros,id_miembro'
        ]);

        $user = auth()->user();
        $role = strtoupper($user->miembro->rol->rol ?? '');

        if ($role !== 'ADMIN' && $role !== 'DIRECTOR') {
            return response()->json(['message' => 'Solo el Director o Administrador pueden realizar reemplazos.'], 403);
        }

        $convocatoria = ConvocatoriaEvento::with('asistencia')->findOrFail($request->id_convocatoria);

        // 1. Verificar si el nuevo miembro ya está convocado para este evento
        $existe = ConvocatoriaEvento::where('id_evento', $convocatoria->id_evento)
            ->where('id_miembro', $request->id_nuevo_miembro)
            ->exists();

        if ($existe) {
            return response()->json(['message' => 'El músico elegido ya está en la formación de este evento.'], 400);
        }

        // 2. Realizar el cambio
        $convocatoria->id_miembro = $request->id_nuevo_miembro;

        // Si hay una asistencia previa, la borramos para que el nuevo marque
        if ($convocatoria->asistencia) {
            $convocatoria->asistencia->delete();
        }

        $convocatoria->save();

        return $convocatoria->load(['miembro.instrumento', 'miembro.seccion']);
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
