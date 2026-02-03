<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class FCMService
{
    /**
     * Envía una notificación Push a un token específico usando FCM V1
     */
    public static function enviarPush($fcmToken, $titulo, $mensaje, $ruta = null)
    {
        if (!$fcmToken) {
            Log::warning("FCM: Token vacío, no se envía notificación");
            return false;
        }

        $projectId = env('FIREBASE_PROJECT_ID');
        if (!$projectId) {
            Log::warning("FIREBASE_PROJECT_ID no configurado en .env");
            return false;
        }

        // Obtener access token OAuth2
        $accessToken = self::getAccessToken();
        if (!$accessToken) {
            Log::error("FCM: No se pudo obtener access token OAuth2");
            return false;
        }

        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        try {
            $response = Http::withToken($accessToken)->post($url, [
                'message' => [
                    'token' => $fcmToken,
                    'notification' => [
                        'title' => $titulo,
                        'body' => $mensaje,
                    ],
                    'data' => [
                        'ruta' => $ruta ?? '/dashboard',
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ],
                    'android' => [
                        'priority' => 'high',
                        'notification' => [
                            'sound' => 'default',
                            'default_vibrate_timings' => true,
                            'default_light_settings' => true,
                            'channel_id' => 'high_importance_channel'
                        ]
                    ],
                ]
            ]);

            if ($response->successful()) {
                Log::info("FCM: Notificación enviada exitosamente a token: " . substr($fcmToken, 0, 20) . "...");
                return true;
            } else {
                Log::error("FCM: Error enviando notificación: " . $response->body());
                return false;
            }
        } catch (\Exception $e) {
            Log::error("FCM: Excepción: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene un access token OAuth2 usando el Service Account JSON
     */
    private static function getAccessToken()
    {
        // Intentar obtener token del cache (dura 1 hora)
        $cachedToken = Cache::get('fcm_access_token');
        if ($cachedToken) {
            return $cachedToken;
        }

        // Ruta al archivo de Service Account
        $serviceAccountPath = storage_path('app/firebase-service-account.json');

        if (!file_exists($serviceAccountPath)) {
            Log::error("FCM: Archivo firebase-service-account.json NO ENCONTRADO en: " . $serviceAccountPath);
            return null;
        }

        try {
            $serviceAccount = json_decode(file_get_contents($serviceAccountPath), true);
            if (!$serviceAccount || !isset($serviceAccount['private_key']) || !isset($serviceAccount['client_email'])) {
                Log::error("FCM: El archivo JSON del Service Account tiene un formato inválido.");
                return null;
            }

            // Crear JWT
            $now = time();
            // Helper function para Base64Url (estándar para JWT)
            $b64Url = function($data) {
                return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
            };

            $header = $b64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $payload = $b64Url(json_encode([
                'iss' => $serviceAccount['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));

            // Firmar JWT
            $privateKey = openssl_pkey_get_private($serviceAccount['private_key']);
            if (!$privateKey) {
                Log::error("FCM: No se pudo obtener la clave privada de openssl. Verifique el formato de 'private_key' en el JSON.");
                return null;
            }

            openssl_sign("$header.$payload", $signature, $privateKey, OPENSSL_ALGO_SHA256);
            $jwt = "$header.$payload." . $b64Url($signature);

            // Intercambiar JWT por access token
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if ($response->successful()) {
                $accessToken = $response->json()['access_token'];
                // Guardar en cache por 50 minutos (el token dura 60 min)
                Cache::put('fcm_access_token', $accessToken, 3000);
                return $accessToken;
            } else {
                Log::error("FCM: Error en respuesta de OAuth2: " . $response->body());
                return null;
            }
        } catch (\Exception $e) {
            Log::error("FCM: Excepción obteniendo token: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Envía notificación a múltiples usuarios
     */
    public static function enviarPushMasivo(array $fcmTokens, $titulo, $mensaje, $ruta = null)
    {
        $sent = 0;
        foreach ($fcmTokens as $token) {
            if (self::enviarPush($token, $titulo, $mensaje, $ruta)) {
                $sent++;
            }
        }
        return $sent;
    }
}

