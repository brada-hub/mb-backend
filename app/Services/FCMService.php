<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FCMService
{
    /**
     * Envía una notificación Push a un token específico usando FCM V1
     */
    public static function enviarPush($fcmToken, $titulo, $mensaje, $ruta = null)
    {
        // NOTA: Para FCM V1 necesitas un Access Token de Google OAuth2.
        // Esto generalmente requiere cargar un JSON de Service Account.
        // Como implementación simplificada, dejamos el log para depuración y la estructura lista.

        Log::info("Enviando Push FCM a: $fcmToken. Título: $titulo. Mensaje: $mensaje");

        if (!$fcmToken) return false;

        // URL de Firebase Cloud Messaging (V1)
        // El PROJECT_ID se obtiene de tu consola de Firebase
        $projectId = env('FIREBASE_PROJECT_ID');
        if (!$projectId) {
            Log::warning("FIREBASE_PROJECT_ID no configurado en .env");
            return false;
        }

        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        // IMPORTANTE: Para producción necesitas generar un token OAuth2 dinámicamente.
        // Aquí simulamos el envío. El usuario completará la lógica de autenticación real.
        /*
        $response = Http::withToken('TU_OAUTH2_ACCESS_TOKEN')->post($url, [
            'message' => [
                'token' => $fcmToken,
                'notification' => [
                    'title' => $titulo,
                    'body' => $mensaje,
                ],
                'data' => [
                    'ruta' => $ruta ?? '/dashboard',
                ],
                'android' => [
                    'priority' => 'high',
                    'notification' => [
                        'click_action' => 'OPEN_ACTIVITY_1',
                        'icon' => 'notification_icon'
                    ]
                ],
                'webpush' => [
                    'fcm_options' => [
                        'link' => $ruta ?? '/dashboard'
                    ]
                ]
            ]
        ]);

        return $response->successful();
        */

        return true;
    }
}
