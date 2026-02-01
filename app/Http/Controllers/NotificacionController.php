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
}
