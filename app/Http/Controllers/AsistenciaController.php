<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Asistencia;
use App\Models\Evento;
use Carbon\Carbon;

class AsistenciaController extends Controller
{
    public function marcar(Request $request)
    {
        $request->validate([
            'id_evento' => 'required|exists:eventos,id_evento',
            'latitud' => 'required',
            'longitud' => 'required'
        ]);

        $user = $request->user();
        $miembro = $user->miembro;

        if (!$miembro) return response()->json(['message' => 'Usuario no es miembro'], 403);

        $evento = Evento::find($request->id_evento);

        // Geofencing Check (Simple distance calculation)
        // ... (Haversine formula implementation usually goes here)

        $now = Carbon::now();
        $horaEvento = Carbon::parse($evento->fecha . ' ' . $evento->hora);
        $diff = $now->diffInMinutes($horaEvento, false); // Negative if late?

        $asistencia = Asistencia::create([
            'id_evento' => $request->id_evento,
            'id_miembro' => $miembro->id_miembro,
            'hora_llegada' => $now->toTimeString(),
            'minutos_retraso' => $diff < 0 ? abs($diff) : 0,
            'estado' => $diff < -15 ? 'RETRASO' : 'PUNTUAL',
            'latitud_marcado' => $request->latitud,
            'longitud_marcado' => $request->longitud,
            'fecha_sincronizacion' => now()
        ]);

        return response()->json($asistencia);
    }

    public function syncOffline(Request $request)
    {
        $request->validate([
            'asistencias' => 'required|array'
        ]);

        $results = [];

        foreach ($request->asistencias as $data) {
            // Check duplicate by UUID
            if (Asistencia::where('offline_uuid', $data['offline_uuid'])->exists()) {
                continue;
            }

            // Create record
            $asistencia = Asistencia::create([
                'id_evento' => $data['id_evento'],
                'id_miembro' => $request->user()->id_miembro, // Or from data if admin syncing
                'hora_llegada' => $data['hora_llegada'],
                'minutos_retraso' => $data['minutos_retraso'], // Should calculate server side or trust device? Trust device for offline time usually if signed.
                'estado' => $data['estado'],
                'offline_uuid' => $data['offline_uuid'],
                'latitud_marcado' => $data['latitud'],
                'longitud_marcado' => $data['longitud'],
                'fecha_sincronizacion' => now()
            ]);

            $results[] = $asistencia->id_asistencia;
        }

        return response()->json(['synced_ids' => $results]);
    }
}
