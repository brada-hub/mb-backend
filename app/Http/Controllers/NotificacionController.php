<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Notificacion;
use Illuminate\Support\Facades\Cache;

class NotificacionController extends Controller
{
    public function index(Request $request)
    {
        return Notificacion::where('id_user', $request->user()->id_user)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();
    }

    public function leer($id)
    {
        $notif = Notificacion::findOrFail($id);
        $notif->leido = true;
        $notif->save();

        // Invalidate cache
        Cache::forget("notif.count.{$notif->id_user}");

        return response()->json(['status' => 'ok']);
    }

    public function unreadCount(Request $request)
    {
        $userId = $request->user()->id_user;

        // Cache per user for 1 minute
        $count = Cache::remember("notif.count.{$userId}", 60, function() use ($userId) {
            return Notificacion::where('id_user', $userId)
                ->where('leido', false)
                ->count();
        });

        return response()->json(['count' => $count]);
    }

    public function marcarTodasLeidas(Request $request)
    {
        $userId = $request->user()->id_user;

        Notificacion::where('id_user', $userId)
            ->where('leido', false)
            ->update(['leido' => true]);

        // Invalidate cache
        Cache::forget("notif.count.{$userId}");

        return response()->json(['status' => 'ok']);
    }

    public function broadcast(Request $request)
    {
        $request->validate([
            'titulo' => 'required|string|max:100',
            'mensaje' => 'required|string',
            'ruta' => 'nullable|string'
        ]);

        $bandaId = auth()->user()->id_banda;
        if (!$bandaId && !auth()->user()->is_super_admin) {
            return response()->json(['message' => 'No perteneces a ninguna banda'], 403);
        }

        // Obtener todos los usuarios de la banda
        $users = \App\Models\User::where('id_banda', $bandaId)
            ->where('estado', true)
            ->get();

        // Generar un ID de referencia único para este envío masivo (batch)
        $batchId = 'BC_' . time();
        $enviados = 0;
        foreach ($users as $user) {
            Notificacion::enviar(
                $user->id_user,
                $request->titulo,
                $request->mensaje,
                $batchId,
                'broadcast',
                $request->ruta ?? '/dashboard'
            );
            $enviados++;
        }

        return response()->json([
            'message' => "Notificación enviada a {$enviados} usuarios.",
            'batch_id' => $batchId
        ]);
    }

    public function getLectores($id_referencia, $tipo)
    {
        // Retorna quiénes han leído una notificación específica vinculada a una referencia
        return Notificacion::where('id_referencia', $id_referencia)
            ->where('tipo', $tipo)
            ->with(['user.miembro'])
            ->select('id_user', 'leido', 'updated_at', 'created_at')
            ->get()
            ->map(function($n) {
                return [
                    'usuario' => $n->user->user ?? 'Desconocido',
                    'nombre' => $n->user->miembro ? ($n->user->miembro->nombres . ' ' . $n->user->miembro->apellidos) : 'N/A',
                    'leido' => $n->leido,
                    'fecha_lectura' => $n->leido ? $n->updated_at->format('d/m H:i') : null
                ];
            });
    }
}
