<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPlanLimits
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $feature = null): Response
    {
        $user = auth()->user();
        if (!$user) return $next($request);

        // SuperAdmin absoluto no tiene restricciones
        if ($user->isSuperAdmin()) return $next($request);

        $banda = $user->banda;
        if (!$banda) return $next($request);

        $plan = strtoupper($banda->plan ?? 'BASIC');

        // Lógica según la funcionalidad solicitada
        switch ($feature) {
            case 'NOTIFICACIONES_PUSH':
                if ($plan === 'BASIC') {
                    return response()->json([
                        'message' => 'Tu plan actual (BASIC) no incluye Notificaciones Push. Mejora a PREMIUM para activar esta función.'
                    ], 403);
                }
                break;

            case 'BIBLIOTECA':
                if ($plan === 'BASIC') {
                    return response()->json([
                        'message' => 'Tu plan actual (BASIC) no incluye acceso a la Biblioteca de Partituras. Mejora a PREMIUM.'
                    ], 403);
                }
                break;

            case 'GESTION_AVANZADA':
                if ($plan === 'BASIC') {
                    return response()->json([
                        'message' => 'Función exclusiva para planes PREMIUM y MONSTER.'
                    ], 403);
                }
                break;
        }

        return $next($request);
    }
}
