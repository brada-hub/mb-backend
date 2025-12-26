<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MiembroController;
use App\Http\Controllers\Api\EventoController;
use App\Http\Controllers\Api\AsistenciaController;
use App\Http\Controllers\Api\RepertorioController;
use App\Http\Controllers\Api\PagoController;
use App\Http\Controllers\Api\CatalogoController;
use App\Http\Controllers\Api\DashboardController;

/*
|══════════════════════════════════════════════════════════════════════
| API Routes - Monster Band Management System
|══════════════════════════════════════════════════════════════════════
*/

// ═══════════════════════════════════════════════════════════════════
// RUTAS PÚBLICAS
// ═══════════════════════════════════════════════════════════════════

// Health check para Docker
Route::get('health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'service' => 'Monster Band API'
    ]);
});

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->name('login');
});


// ═══════════════════════════════════════════════════════════════════
// RUTAS PROTEGIDAS (Requieren autenticación)
// ═══════════════════════════════════════════════════════════════════
Route::middleware('auth:sanctum')->group(function () {

    // ─── Autenticación ───
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('cambiar-password', [AuthController::class, 'cambiarPassword']);
        Route::get('perfil', [AuthController::class, 'perfil']);
        Route::put('perfil', [AuthController::class, 'actualizarPerfil']);
    });

    // ─── Dashboard ───
    Route::prefix('dashboard')->group(function () {
        Route::get('general', [DashboardController::class, 'general']);
        Route::get('miembro', [DashboardController::class, 'miembro']);
        Route::get('seccion/{seccion}', [DashboardController::class, 'porSeccion']);
        Route::get('mapa-miembros', [DashboardController::class, 'mapaMiembros']);
    });

    // ─── Catálogos ───
    Route::prefix('catalogos')->group(function () {
        Route::get('todos', [CatalogoController::class, 'todos']);

        Route::get('secciones', [CatalogoController::class, 'secciones']);
        Route::post('secciones', [CatalogoController::class, 'crearSeccion']);
        Route::put('secciones/{seccion}', [CatalogoController::class, 'actualizarSeccion']);

        Route::get('categorias', [CatalogoController::class, 'categorias']);
        Route::post('categorias', [CatalogoController::class, 'crearCategoria']);
        Route::put('categorias/{categoria}', [CatalogoController::class, 'actualizarCategoria']);

        Route::get('roles', [CatalogoController::class, 'roles']);

        Route::get('tarifas', [CatalogoController::class, 'tarifas']);
        Route::post('tarifas', [CatalogoController::class, 'actualizarTarifa']);
    });

    // ─── Miembros ───
    Route::prefix('miembros')->group(function () {
        Route::get('/', [MiembroController::class, 'index']);
        Route::post('/', [MiembroController::class, 'store']);
        Route::get('/seccion/{seccionId}', [MiembroController::class, 'porSeccion']);
        Route::get('/{miembro}', [MiembroController::class, 'show']);
        Route::put('/{miembro}', [MiembroController::class, 'update']);
        Route::delete('/{miembro}', [MiembroController::class, 'destroy']);
        Route::post('/{miembro}/restablecer-password', [MiembroController::class, 'restablecerPassword']);
        Route::post('/{miembro}/cambiar-dispositivo', [MiembroController::class, 'cambiarDispositivo']);
        Route::get('/{miembro}/extracto', [MiembroController::class, 'extracto']);
    });

    // ─── Eventos ───
    Route::prefix('eventos')->group(function () {
        Route::get('/', [EventoController::class, 'index']);
        Route::post('/', [EventoController::class, 'store']);
        Route::get('/mis-eventos', [EventoController::class, 'misEventos']);
        Route::get('/{evento}', [EventoController::class, 'show']);
        Route::put('/{evento}', [EventoController::class, 'update']);
        Route::delete('/{evento}', [EventoController::class, 'destroy']);

        // Gestión de lista
        Route::get('/{evento}/lista', [EventoController::class, 'obtenerLista']);
        Route::post('/{evento}/agregar-miembro', [EventoController::class, 'agregarMiembro']);
        Route::delete('/{evento}/quitar-miembro/{miembroId}', [EventoController::class, 'quitarMiembro']);
        Route::post('/{evento}/confirmar-lista', [EventoController::class, 'confirmarLista']);
    });

    // ─── Asistencias ───
    Route::prefix('asistencias')->group(function () {
        Route::post('/registrar', [AsistenciaController::class, 'registrar']);
        Route::post('/registrar-manual', [AsistenciaController::class, 'registrarManual']);
        Route::get('/evento/{evento}', [AsistenciaController::class, 'porEvento']);
        Route::get('/mi-historial', [AsistenciaController::class, 'miHistorial']);
        Route::post('/evento/{evento}/marcar-ausentes', [AsistenciaController::class, 'marcarAusentes']);
    });

    // ─── Repertorio ───
    Route::prefix('repertorio')->group(function () {
        Route::get('/mis-partituras', [RepertorioController::class, 'misPartituras']);

        Route::get('/generos', [RepertorioController::class, 'generos']);
        Route::post('/generos', [RepertorioController::class, 'crearGenero']);
        Route::put('/generos/{genero}', [RepertorioController::class, 'actualizarGenero']);

        Route::get('/generos/{genero}/temas', [RepertorioController::class, 'temas']);
        Route::post('/generos/{genero}/temas', [RepertorioController::class, 'crearTema']);
        Route::put('/temas/{tema}', [RepertorioController::class, 'actualizarTema']);
        Route::get('/temas/{tema}', [RepertorioController::class, 'verTema']);

        Route::get('/temas/{tema}/partituras', [RepertorioController::class, 'partituras']);
        Route::post('/temas/{tema}/partituras', [RepertorioController::class, 'subirPartitura']);
        Route::delete('/partituras/{partitura}', [RepertorioController::class, 'eliminarPartitura']);
    });

    // ─── Pagos ───
    Route::prefix('pagos')->group(function () {
        Route::get('/mi-estado-cuenta', [PagoController::class, 'miEstadoCuenta']);
        Route::get('/historial', [PagoController::class, 'historial']);
        Route::get('/deudas', [PagoController::class, 'resumenDeudas']);

        Route::get('/evento/{evento}/liquidaciones', [PagoController::class, 'liquidacionesEvento']);
        Route::post('/evento/{evento}/generar-liquidaciones', [PagoController::class, 'generarLiquidaciones']);

        Route::post('/registrar', [PagoController::class, 'registrarPago']);
        Route::post('/masivo', [PagoController::class, 'pagoMasivo']);
        Route::post('/{pago}/anular', [PagoController::class, 'anularPago']);
    });
});
