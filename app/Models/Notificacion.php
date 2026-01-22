<?php

namespace App\Models;

use App\Traits\BelongsToBanda;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notificacion extends Model
{
    use HasFactory, BelongsToBanda;
    protected $table = 'notificaciones';
    protected $primaryKey = 'id_notificacion';
    protected $fillable = ['id_user', 'titulo', 'mensaje', 'leido', 'id_referencia', 'tipo', 'ruta', 'id_banda'];

    /**
     * Helper para enviar una notificación rápidamente
     */
    public static function enviar($id_user, $titulo, $mensaje, $id_referencia = null, $tipo = null, $ruta = null)
    {
        // 1. Verificar preferencias del usuario (Silent Mode)
        $user = User::find($id_user);
        if ($user && $tipo && $user->preferencias_notificaciones) {
            $prefs = $user->preferencias_notificaciones;
            // Si el tipo está explícitamente desactivado (false), no enviamos
            if (isset($prefs[$tipo]) && $prefs[$tipo] === false) {
                return null;
            }
        }

        // 2. Anti-spam: No enviar la misma notificación al mismo usuario en los últimos 5 minutos
        if ($tipo && $id_referencia) {
            $reciente = self::where('id_user', $id_user)
                ->where('tipo', $tipo)
                ->where('id_referencia', $id_referencia)
                ->where('created_at', '>=', now()->subMinutes(5))
                ->first();

            if ($reciente) return $reciente;
        }

        $notificacion = self::create([
            'id_user' => $id_user,
            'titulo' => $titulo,
            'mensaje' => $mensaje,
            'id_referencia' => $id_referencia,
            'tipo' => $tipo,
            'ruta' => $ruta,
            'id_banda' => $user->id_banda ?? null,
            'leido' => false
        ]);

        // 3. Notificación Push Real (Firebase)
        if ($notificacion && $user && $user->fcm_token) {
            \App\Services\FCMService::enviarPush(
                $user->fcm_token,
                $titulo,
                $mensaje,
                $ruta
            );
        }

        return $notificacion;
    }
}
