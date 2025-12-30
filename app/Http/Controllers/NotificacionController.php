<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Notificacion;

class NotificacionController extends Controller
{
    public function index(Request $request)
    {
        return Notificacion::where('id_user', $request->user()->id_user)->get();
    }

    public function leer($id)
    {
        $notif = Notificacion::find($id);
        if($notif) {
             $notif->leido = true;
             $notif->save();
        }
        return response()->json($notif);
    }
}
