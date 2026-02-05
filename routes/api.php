<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MiembroController;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\AsistenciaController;
use App\Http\Controllers\RecursoController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\SeccionController;
use App\Http\Controllers\GeneroController;
use App\Http\Controllers\TemaController;
use App\Http\Controllers\VozInstrumentalController;
use App\Http\Controllers\MixController;
use App\Http\Controllers\DashboardController;

Route::get('/test-roles', function() { return response()->json(['status' => 'ok']); });
Route::get('/test-push', function() {
    $user = auth()->user();
    if (!$user->fcm_token) return response()->json(['error' => 'No FCM token for user'], 400);
    $sent = \App\Services\FCMService::enviarPush($user->fcm_token, "Prueba", "Esta es una notificación de prueba");
    return response()->json(['sent' => $sent]);
})->middleware('auth:sanctum');

// Public Auth routes only (login, check-device)
Route::post('/login', [AuthController::class, 'login']);
Route::post('/check-device', [AuthController::class, 'checkDevice']);
Route::get('/branding/{slug}', [\App\Http\Controllers\BandaController::class, 'getBranding']);
Route::post('/cleanup-test-member', [MiembroController::class, 'cleanupTestMember']);
Route::post('/cleanup-test-data', [SeccionController::class, 'cleanupTestData']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::post('/complete-profile', [AuthController::class, 'completeProfile']);
    Route::post('/update-preferences', [AuthController::class, 'updatePreferences']);
    Route::post('/update-theme', [AuthController::class, 'updateTheme']);
    Route::post('/update-password', [AuthController::class, 'updatePassword']);
    Route::post('/update-fcm-token', [AuthController::class, 'updateFCMToken']);

    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'getStats']);

    // Sync
    Route::get('/sync/versions', [AuthController::class, 'syncVersions']);
    Route::post('/sync/master-data', [AuthController::class, 'syncMasterData']);

    // Miembros
    Route::apiResource('miembros', MiembroController::class);
    Route::apiResource('instrumentos', \App\Http\Controllers\InstrumentoController::class);
    Route::post('/miembros/{id}/toggle-status', [MiembroController::class, 'toggleStatus']);
    Route::get('/miembros/{id}/dispositivos', [MiembroController::class, 'getDevices']);
    Route::put('/miembros/{id}/limite-dispositivos', [MiembroController::class, 'updateDeviceLimit']);
    Route::post('/miembros/{id}/reset-password', [MiembroController::class, 'resetPassword']);
    Route::delete('/dispositivos/{id}', [MiembroController::class, 'deleteDevice']);

    // Roles y Secciones
    Route::apiResource('roles', RolController::class);
    Route::get('/permisos-lista', [RolController::class, 'getPermisos']);
    Route::apiResource('secciones', SeccionController::class);
    // Route::apiResource('instrumentos', ...); // Moved to public

    // Biblioteca Musical (Biblioteca de Partituras)
    Route::middleware('plan.limits:BIBLIOTECA')->group(function () {
        Route::apiResource('generos', GeneroController::class);
        Route::post('/generos/reorder', [GeneroController::class, 'reorder']);
        Route::apiResource('temas', TemaController::class);
        Route::apiResource('voces', VozInstrumentalController::class);
        Route::apiResource('recursos', RecursoController::class);
        Route::apiResource('mixes', MixController::class);
    });
    Route::post('/asistencia/marcar', [AsistenciaController::class, 'marcar']);
    Route::post('/asistencia/sync-offline', [AsistenciaController::class, 'syncOffline']);
    Route::get('/asistencia/reporte/{id_evento}', [AsistenciaController::class, 'reporte']);

    // Rutas para control de asistencia (Admin/Director)
    Route::get('/asistencia/eventos-hoy', [AsistenciaController::class, 'eventosHoy']);
    Route::get('/asistencia/lista/{id_evento}', [AsistenciaController::class, 'listaAsistencia']);
    Route::post('/asistencia/marcar-manual', [AsistenciaController::class, 'marcarManual']);
    Route::post('/asistencia/marcar-masivo', [AsistenciaController::class, 'marcarMasivo']);
    Route::post('/asistencia/cerrar', [AsistenciaController::class, 'cerrarAsistencia']);
    Route::post('/asistencia/recordatorio', [AsistenciaController::class, 'enviarRecordatorios']);

    // Analytics Asistencia
    Route::get('/asistencias/stats', [\App\Http\Controllers\AsistenciaStatsController::class, 'globalStats']);
    Route::get('/asistencias/member/{id}', [\App\Http\Controllers\AsistenciaStatsController::class, 'memberStats']);
    Route::get('/asistencias/reporte-grupal', [\App\Http\Controllers\AsistenciaStatsController::class, 'groupReport']);
    Route::get('/asistencias/reporte-matrix', [\App\Http\Controllers\AsistenciaStatsController::class, 'reportMatrix']);
    Route::get('/asistencias/reporte-grupal/pdf', [\App\Http\Controllers\AsistenciaStatsController::class, 'downloadGroupReportPdf']);
    Route::get('/asistencias/rankings', [\App\Http\Controllers\AsistenciaStatsController::class, 'getRankings']);

    // Convocatorias
    Route::get('/convocatorias', [\App\Http\Controllers\ConvocatoriaController::class, 'index']);
    Route::get('/convocatorias/disponibles', [\App\Http\Controllers\ConvocatoriaController::class, 'miembrosParaPostular']);
    Route::post('/convocatorias/postular', [\App\Http\Controllers\ConvocatoriaController::class, 'postular']);
    Route::post('/convocatorias/confirmar', [\App\Http\Controllers\ConvocatoriaController::class, 'confirmar']);
    Route::post('/convocatorias/confirmar-masivo', [\App\Http\Controllers\ConvocatoriaController::class, 'confirmarMasivo']);
    Route::post('/convocatorias/responder', [\App\Http\Controllers\ConvocatoriaController::class, 'confirmarMiembro']);
    Route::post('/convocatorias/reemplazar', [\App\Http\Controllers\ConvocatoriaController::class, 'reemplazar']);
    Route::post('/convocatorias/vincular-formacion', [\App\Http\Controllers\ConvocatoriaController::class, 'vincularFormacion']);
    Route::delete('/convocatorias/{id}', [\App\Http\Controllers\ConvocatoriaController::class, 'destroy']);

    // Formaciones (Plantillas de Personal)
    Route::apiResource('formaciones', \App\Http\Controllers\FormacionController::class);
    Route::post('/formaciones/{id}/toggle-activo', [\App\Http\Controllers\FormacionController::class, 'toggleActivo']);

    // Eventos
    Route::get('/eventos/tipos', [EventoController::class, 'getTipos']);
    Route::post('/eventos/tipos', [EventoController::class, 'storeTipo']);
    Route::get('/eventos/proximos', [EventoController::class, 'proximos']);
    Route::get('/eventos/proximas-convocatorias', [EventoController::class, 'proximasConvocatorias']);
    // Route::get('/eventos/tipos'... Moved to public
    // These might be redundant if using ConvocatoriaController but keeping for now
    Route::get('/eventos/{id}/convocatoria', [EventoController::class, 'convocatoria']);
    Route::patch('/eventos/{id}/confirmar', [EventoController::class, 'confirmar']);
    Route::apiResource('eventos', EventoController::class);
    Route::post('/eventos/{id}/solicitar-listas', [EventoController::class, 'solicitarListas']);

    // Recursos
    Route::get('/recursos', [RecursoController::class, 'index']);
    Route::get('/recursos/download/{id}', [RecursoController::class, 'download']);
    Route::post('/recursos', [RecursoController::class, 'store']);

    // Pagos
    Route::get('/pagos/deudas', [\App\Http\Controllers\PagosController::class, 'resumenDeudas']);
    Route::get('/pagos/deudas/{id_miembro}', [\App\Http\Controllers\PagosController::class, 'detalleDeuda']);
    Route::post('/pagos/pagar', [\App\Http\Controllers\PagosController::class, 'pagar']);
    Route::get('/pagos/mis-pagos', [\App\Http\Controllers\PagosController::class, 'miHistorial']);
    Route::get('/pagos/reporte-pdf', [\App\Http\Controllers\PagosController::class, 'generarReportePDF']);

    // Notificaciones
    Route::get('/notificaciones', [NotificacionController::class, 'index']);
    Route::get('/notificaciones/unread-count', [NotificacionController::class, 'unreadCount']);
    Route::post('/notificaciones/marcar-todas', [NotificacionController::class, 'marcarTodasLeidas']);
    Route::patch('/notificaciones/{id}/leer', [NotificacionController::class, 'leer']);
    Route::post('/notificaciones/broadcast', [NotificacionController::class, 'broadcast']);
    Route::get('/notificaciones/seguimiento/{id_referencia}/{tipo}', [NotificacionController::class, 'getLectores']);

    // ========== SUPER ADMIN ROUTES ==========
    Route::prefix('superadmin')->group(function () {
        // SUPER ENDPOINT: Todo el dashboard en una sola petición
        Route::get('/dashboard', [\App\Http\Controllers\SuperAdminController::class, 'getDashboardData']);

        Route::get('/stats', [\App\Http\Controllers\SuperAdminController::class, 'getStats']);
        Route::get('/bandas', [\App\Http\Controllers\SuperAdminController::class, 'listBandas']);
        Route::post('/bandas', [\App\Http\Controllers\SuperAdminController::class, 'createBanda']);
        Route::put('/bandas/{id}', [\App\Http\Controllers\SuperAdminController::class, 'updateBanda']);
        Route::post('/bandas/{id}/admin', [\App\Http\Controllers\SuperAdminController::class, 'createBandaAdmin']);
        Route::post('/impersonate/{id}', [\App\Http\Controllers\SuperAdminController::class, 'impersonateBanda']);
        Route::post('/stop-impersonate', [\App\Http\Controllers\SuperAdminController::class, 'stopImpersonating']);
        Route::get('/audit-logs', [\App\Http\Controllers\SuperAdminController::class, 'getAuditLogs']);
        Route::get('/storage-report', [\App\Http\Controllers\SuperAdminController::class, 'getStorageReport']);

        // Gestión de Planes
        Route::get('/plans', [\App\Http\Controllers\SuperAdminController::class, 'listPlans']);
        Route::post('/plans', [\App\Http\Controllers\SuperAdminController::class, 'storePlan']);
        Route::put('/plans/{id}', [\App\Http\Controllers\SuperAdminController::class, 'updatePlan']);
        Route::delete('/plans/{id}', [\App\Http\Controllers\SuperAdminController::class, 'deletePlan']);
    });
});
