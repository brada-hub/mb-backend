<?php

namespace App\Http\Controllers;

use App\Models\ConvocatoriaEvento;
use App\Models\Miembro;
use App\Models\Evento;
use App\Models\Formacion;
use Illuminate\Http\Request;

class ConvocatoriaController extends Controller
{
    /**
     * Vincular una formación completa a un evento
     */
    public function vincularFormacion(Request $request)
    {
        $validated = $request->validate([
            'id_evento' => 'required|exists:eventos,id_evento',
            'id_formacion' => 'required|exists:formaciones,id_formacion'
        ]);

        $formacion = Formacion::with('miembros')->findOrFail($request->id_formacion);

        $nuevasConvocatorias = [];
        foreach ($formacion->miembros as $miembro) {
            $nuevasConvocatorias[] = ConvocatoriaEvento::updateOrCreate(
                [
                    'id_evento' => $request->id_evento,
                    'id_miembro' => $miembro->id_miembro
                ],
                [
                    // Por defecto las formaciones predefinidas suelen ser de confianza,
                    // pero mantenemos confirmado_por_director en false para revisión final si se prefiere.
                    'confirmado_por_director' => false
                ]
            );
        }

        return response()->json([
            'message' => 'Formación vinculada correctamente',
            'count' => count($nuevasConvocatorias)
        ]);
    }
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

        $convocatoria = ConvocatoriaEvento::with(['miembro.user', 'evento'])->findOrFail($request->id_convocatoria);
        $convocatoria->confirmado_por_director = true;
        $convocatoria->save();

        if ($convocatoria->miembro && $convocatoria->miembro->user) {
            \App\Models\Notificacion::enviar(
                $convocatoria->miembro->user->id_user,
                "¡Nueva Convocatoria!",
                "Fuiste seleccionado para el evento: {$convocatoria->evento->evento}. Revisa tu agenda.",
                $convocatoria->id_evento,
                'convocatoria',
                "/dashboard/eventos/{$convocatoria->id_evento}/convocatoria"
            );
        }

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

        $convocatorias = ConvocatoriaEvento::with(['miembro.user', 'evento'])
            ->whereIn('id_convocatoria', $request->id_convocatorias)
            ->get();

        // Agrupar por usuario para no spamear
        $notificacionesPorUsuario = [];

        foreach ($convocatorias as $conv) {
            $conv->update(['confirmado_por_director' => true]);

            if ($conv->miembro && $conv->miembro->user) {
                $userId = $conv->miembro->user->id_user;
                if (!isset($notificacionesPorUsuario[$userId])) {
                    $notificacionesPorUsuario[$userId] = [
                        'eventos' => [],
                        'user' => $conv->miembro->user
                    ];
                }
                $notificacionesPorUsuario[$userId]['eventos'][] = $conv->evento->evento;
            }
        }

        foreach ($notificacionesPorUsuario as $userId => $data) {
            $count = count($data['eventos']);
            $nombres = implode(', ', array_slice($data['eventos'], 0, 3));
            if ($count > 3) $nombres .= "... y otros";

            $mensaje = $count > 1
                ? "Has sido confirmado para {$count} eventos: {$nombres}."
                : "Has sido confirmado para el evento: {$data['eventos'][0]}.";

            \App\Models\Notificacion::enviar(
                $userId,
                "¡Nuevas Convocatorias!",
                $mensaje,
                null, // id_referencia nulo para agrupación
                'convocatoria',
                "/dashboard/eventos"
            );
        }

        return response()->json(['status' => 'ok']);
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
     * Músico confirma o rechaza su asistencia
     */
    public function confirmarMiembro(Request $request)
    {
        $request->validate([
            'id_convocatoria' => 'required|exists:convocatoria_evento,id_convocatoria',
            'confirmado' => 'required|boolean'
        ]);

        $user = auth()->user();
        $convocatoria = ConvocatoriaEvento::findOrFail($request->id_convocatoria);

        // Seguridad: Solo el dueño de la convocatoria puede confirmar
        if ($convocatoria->id_miembro !== $user->miembro->id_miembro) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $convocatoria->confirmado_por_miembro = $request->confirmado;
        $convocatoria->save();

        return response()->json([
            'status' => 'ok',
            'confirmado' => $convocatoria->confirmado_por_miembro
        ]);
    }

    /**
     * Remove from convocatoria
     */
    public function destroy($id)
    {
        $convocatoria = ConvocatoriaEvento::with(['miembro.user', 'evento'])->findOrFail($id);

        // Notificar al músico antes de eliminar
        if ($convocatoria->miembro && $convocatoria->miembro->user) {
            \App\Models\Notificacion::enviar(
                $convocatoria->miembro->user->id_user,
                "Participación Cancelada",
                "Tu participación en el evento '{$convocatoria->evento->evento}' ha sido cancelada.",
                $convocatoria->id_evento,
                'cancelacion',
                '/dashboard/agenda'
            );
        }

        $convocatoria->delete();
        return response()->json(['message' => 'Eliminado correctamente'], 200);
    }
}
