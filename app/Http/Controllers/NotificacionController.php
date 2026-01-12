<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Notificacion;

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

        return response()->json(['status' => 'ok']);
    }

    public function unreadCount(Request $request)
    {
        $count = Notificacion::where('id_user', $request->user()->id_user)
            ->where('leido', false)
            ->count();

        return response()->json(['count' => $count]);
    }

    public function marcarTodasLeidas(Request $request)
    {
        Notificacion::where('id_user', $request->user()->id_user)
            ->where('leido', false)
            ->update(['leido' => true]);

        return response()->json(['status' => 'ok']);
    }
}
