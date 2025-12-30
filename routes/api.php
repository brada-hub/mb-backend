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

Route::post('/login', [AuthController::class, 'login']);
Route::post('/check-device', [AuthController::class, 'checkDevice']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'profile']);

    // Sync
    Route::get('/sync/versions', [AuthController::class, 'syncVersions']);
    Route::get('/sync/master-data', [AuthController::class, 'syncMasterData']);
    Route::get('/sync/eventos', [EventoController::class, 'syncEventos']);

    // Miembros
    Route::apiResource('miembros', MiembroController::class);
    Route::get('/roles-permisos', [MiembroController::class, 'rolesPermisos']);

    // Asistencia
    Route::post('/asistencia/marcar', [AsistenciaController::class, 'marcar']);
    Route::post('/asistencia/sync-offline', [AsistenciaController::class, 'syncOffline']);
    Route::get('/asistencia/reporte/{id_evento}', [AsistenciaController::class, 'reporte']);

    // Eventos
    Route::get('/eventos/proximos', [EventoController::class, 'proximos']);
    Route::get('/eventos/{id}/convocatoria', [EventoController::class, 'convocatoria']);
    Route::patch('/eventos/{id}/confirmar', [EventoController::class, 'confirmar']);
    Route::apiResource('eventos', EventoController::class)->except(['index', 'show']); // Custom index defined above or implied standard

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
