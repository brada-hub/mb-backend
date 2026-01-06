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

Route::get('/test-roles', function() { return response()->json(['status' => 'ok']); });

Route::post('/login', [AuthController::class, 'login']);
Route::post('/check-device', [AuthController::class, 'checkDevice']);
Route::post('/cleanup-test-member', [MiembroController::class, 'cleanupTestMember']);
Route::post('/cleanup-test-data', [SeccionController::class, 'cleanupTestData']);

Route::get('/eventos/tipos', [EventoController::class, 'getTipos']);
Route::apiResource('instrumentos', \App\Http\Controllers\InstrumentoController::class);
Route::apiResource('miembros', MiembroController::class);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    // Sync
    Route::get('/sync/versions', [AuthController::class, 'syncVersions']);
    Route::post('/sync/master-data', [AuthController::class, 'syncMasterData']);

    // Miembros
    // Route::apiResource('miembros', MiembroController::class); // Moved to public
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
    Route::apiResource('generos', GeneroController::class);
    Route::post('/generos/reorder', [GeneroController::class, 'reorder']);
    Route::apiResource('temas', TemaController::class);
    Route::apiResource('voces', VozInstrumentalController::class);
    Route::apiResource('recursos', RecursoController::class);
    Route::post('/asistencia/marcar', [AsistenciaController::class, 'marcar']);
    Route::post('/asistencia/sync-offline', [AsistenciaController::class, 'syncOffline']);
    Route::get('/asistencia/reporte/{id_evento}', [AsistenciaController::class, 'reporte']);

    // Rutas para control de asistencia (Admin/Director)
    Route::get('/asistencia/eventos-hoy', [AsistenciaController::class, 'eventosHoy']);
    Route::get('/asistencia/lista/{id_evento}', [AsistenciaController::class, 'listaAsistencia']);
    Route::post('/asistencia/marcar-manual', [AsistenciaController::class, 'marcarManual']);
    Route::post('/asistencia/marcar-masivo', [AsistenciaController::class, 'marcarMasivo']);

    // Convocatorias
    Route::get('/convocatorias', [\App\Http\Controllers\ConvocatoriaController::class, 'index']);
    Route::get('/convocatorias/disponibles', [\App\Http\Controllers\ConvocatoriaController::class, 'miembrosParaPostular']);
    Route::post('/convocatorias/postular', [\App\Http\Controllers\ConvocatoriaController::class, 'postular']);
    Route::post('/convocatorias/confirmar', [\App\Http\Controllers\ConvocatoriaController::class, 'confirmar']);
    Route::post('/convocatorias/confirmar-masivo', [\App\Http\Controllers\ConvocatoriaController::class, 'confirmarMasivo']);
    Route::delete('/convocatorias/{id}', [\App\Http\Controllers\ConvocatoriaController::class, 'destroy']);

    // Eventos
    Route::get('/eventos/proximos', [EventoController::class, 'proximos']);
    // Route::get('/eventos/tipos'... Moved to public
    // These might be redundant if using ConvocatoriaController but keeping for now
    Route::get('/eventos/{id}/convocatoria', [EventoController::class, 'convocatoria']);
    Route::patch('/eventos/{id}/confirmar', [EventoController::class, 'confirmar']);
    Route::apiResource('eventos', EventoController::class);

    // Recursos
    Route::get('/recursos', [RecursoController::class, 'index']);
    Route::get('/recursos/download/{id}', [RecursoController::class, 'download']);
    Route::post('/recursos', [RecursoController::class, 'store']);

    // Notificaciones
    Route::get('/notificaciones', [NotificacionController::class, 'index']);
    Route::patch('/notificaciones/{id}/leer', [NotificacionController::class, 'leer']);
});
