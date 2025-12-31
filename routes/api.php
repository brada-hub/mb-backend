<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MiembroController;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\AsistenciaController;
use App\Http\Controllers\RecursoController;
use App\Http\Controllers\FinanzaController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\SeccionController;

Route::get('/test-roles', function() { return response()->json(['status' => 'ok']); });

Route::post('/login', [AuthController::class, 'login']);
Route::post('/check-device', [AuthController::class, 'checkDevice']);
Route::post('/cleanup-test-member', [MiembroController::class, 'cleanupTestMember']);
Route::post('/cleanup-test-data', [SeccionController::class, 'cleanupTestData']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    // Sync
    Route::get('/sync/versions', [AuthController::class, 'syncVersions']);
    Route::post('/sync/master-data', [AuthController::class, 'syncMasterData']);

    // Miembros
    Route::apiResource('miembros', MiembroController::class);
    Route::post('/miembros/{id}/toggle-status', [MiembroController::class, 'toggleStatus']);

    // Roles y Secciones
    Route::apiResource('roles', RolController::class);
    Route::get('/permisos-lista', [RolController::class, 'getPermisos']);
    Route::apiResource('secciones', SeccionController::class);

    // Asistencia
    Route::post('/asistencia/marcar', [AsistenciaController::class, 'marcar']);
    Route::post('/asistencia/sync-offline', [AsistenciaController::class, 'syncOffline']);
    Route::get('/asistencia/reporte/{id_evento}', [AsistenciaController::class, 'reporte']);

    // Eventos
    Route::get('/eventos/proximos', [EventoController::class, 'proximos']);
    Route::get('/eventos/{id}/convocatoria', [EventoController::class, 'convocatoria']);
    Route::patch('/eventos/{id}/confirmar', [EventoController::class, 'confirmar']);
    Route::apiResource('eventos', EventoController::class)->except(['index', 'show']);

    // Recursos
    Route::get('/recursos', [RecursoController::class, 'index']);
    Route::get('/recursos/download/{id}', [RecursoController::class, 'download']);
    Route::post('/recursos', [RecursoController::class, 'store']);

    // Finanzas
    Route::get('/finanzas/configuracion', [FinanzaController::class, 'configuracion']);
    Route::get('/finanzas/liquidacion/{id_evento}', [FinanzaController::class, 'liquidacion']);
    Route::get('/mi-sueldo', [FinanzaController::class, 'miSueldo']);

    // Notificaciones
    Route::get('/notificaciones', [NotificacionController::class, 'index']);
    Route::patch('/notificaciones/{id}/leer', [NotificacionController::class, 'leer']);
});
