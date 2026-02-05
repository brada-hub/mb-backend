<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\Evento;
use App\Models\Notificacion;
use Carbon\Carbon;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Recordatorio automático de eventos (2 horas antes)
Schedule::call(function () {
    // Buscar eventos en las próximas 2 horas (Margen de 15 minutos)
    $now = Carbon::now();
    $targetTime = $now->copy()->addHours(2);

    // Buscamos eventos que caen en este rango de tiempo para hoy/mañana
    $eventos = Evento::where('fecha', $targetTime->toDateString())
        ->where('hora', '>=', $targetTime->copy()->subMinutes(10)->toTimeString())
        ->where('hora', '<=', $targetTime->copy()->addMinutes(10)->toTimeString())
        ->where('estado', true)
        ->with('convocatorias.miembro.user')
        ->get();

    foreach ($eventos as $evento) {
        foreach ($evento->convocatorias as $conv) {
            if ($conv->miembro && $conv->miembro->user && $conv->confirmado_por_director) {
                // Notificar: "Recordatorio: [Evento] empieza en 2 horas"
                Notificacion::enviar(
                    $conv->miembro->user->id_user,
                    "⏰ Recordatorio: {$evento->evento}",
                    "Te recordamos que el evento empieza en 2 horas (a las " . Carbon::parse($evento->hora)->format('H:i') . "). ¡No olvides tu instrumento!",
                    $evento->id_evento,
                    'recordatorio_evento',
                    '/dashboard/agenda'
                );
            }
        }
    }
})->everyFifteenMinutes();
