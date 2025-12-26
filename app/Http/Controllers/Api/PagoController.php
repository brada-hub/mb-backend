<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\Liquidacion;
use App\Models\Pago;
use App\Models\Miembro;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PagoController extends Controller
{
    /**
     * ═══════════════════════════════════════════════════════════
     * GENERAR LIQUIDACIONES DEL EVENTO
     * ═══════════════════════════════════════════════════════════
     */
    public function generarLiquidaciones(Evento $evento): JsonResponse
    {
        if ($evento->estado !== Evento::ESTADO_FINALIZADO) {
            return response()->json([
                'success' => false,
                'message' => 'El evento debe estar finalizado para generar liquidaciones',
            ], 422);
        }

        Liquidacion::generarParaEvento($evento);

        $liquidaciones = $evento->liquidaciones()
            ->with('miembro')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Liquidaciones generadas correctamente',
            'data' => [
                'total' => $liquidaciones->count(),
                'monto_total' => $liquidaciones->sum('monto_final'),
                'liquidaciones' => $liquidaciones,
            ],
        ]);
    }

    /**
     * ═══════════════════════════════════════════════════════════
     * VER LIQUIDACIONES DE UN EVENTO
     * ═══════════════════════════════════════════════════════════
     */
    public function liquidacionesEvento(Evento $evento): JsonResponse
    {
        $liquidaciones = $evento->liquidaciones()
            ->with(['miembro.seccion', 'miembro.categoria'])
            ->get();

        $resumen = [
            'total_miembros' => $liquidaciones->count(),
            'monto_base_total' => $liquidaciones->sum('monto_base'),
            'descuentos_total' => $liquidaciones->sum('total_descuentos'),
            'monto_final_total' => $liquidaciones->sum('monto_final'),
            'pendientes' => $liquidaciones->where('estado', 'pendiente')->count(),
            'pagados' => $liquidaciones->where('estado', 'pagado')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'evento' => $evento->only(['id', 'nombre', 'fecha', 'tipo']),
                'resumen' => $resumen,
                'liquidaciones' => $liquidaciones,
            ],
        ]);
    }

    /**
     * ═══════════════════════════════════════════════════════════
     * REGISTRAR PAGO INDIVIDUAL
     * ═══════════════════════════════════════════════════════════
     */
    public function registrarPago(Request $request): JsonResponse
    {
        $request->validate([
            'miembro_id' => 'required|exists:miembros,id',
            'monto' => 'required|numeric|min:0.01',
            'metodo' => 'required|in:efectivo,transferencia,qr',
            'referencia' => 'nullable|string|max:100',
            'observaciones' => 'nullable|string',
            'liquidacion_ids' => 'nullable|array',
            'liquidacion_ids.*' => 'exists:liquidaciones,id',
        ]);

        $pago = Pago::create([
            'miembro_id' => $request->miembro_id,
            'monto' => $request->monto,
            'metodo' => $request->metodo,
            'referencia' => $request->referencia,
            'fecha_pago' => now(),
            'observaciones' => $request->observaciones,
            'observaciones' => $request->observaciones,
            'registrado_por' => $request->user()->miembro ? $request->user()->miembro->id : throw new \Exception('El usuario no tiene un perfil de miembro asociado para registrar pagos.'),
        ]);

        // Aplicar a liquidaciones
        $pago->aplicarALiquidaciones($request->get('liquidacion_ids', []));

        $pago->load(['miembro', 'liquidaciones.liquidacion']);

        return response()->json([
            'success' => true,
            'message' => 'Pago registrado correctamente',
            'data' => $pago,
        ], 201);
    }

    /**
     * ═══════════════════════════════════════════════════════════
     * PAGO MASIVO (Todos los miembros de un evento)
     * ═══════════════════════════════════════════════════════════
     */
    public function pagoMasivo(Request $request): JsonResponse
    {
        $request->validate([
            'evento_id' => 'required|exists:eventos,id',
            'metodo' => 'required|in:efectivo,transferencia,qr',
            'referencia' => 'nullable|string|max:100',
        ]);

        $evento = Evento::findOrFail($request->evento_id);

        $liquidaciones = $evento->liquidaciones()
            ->where('estado', Liquidacion::ESTADO_PENDIENTE)
            ->get();

        if ($liquidaciones->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No hay liquidaciones pendientes para este evento',
            ], 422);
        }

        $pagosRealizados = [];

        foreach ($liquidaciones as $liquidacion) {
            $pago = Pago::create([
                'miembro_id' => $liquidacion->miembro_id,
                'monto' => $liquidacion->monto_final,
                'metodo' => $request->metodo,
                'referencia' => $request->referencia,
                'fecha_pago' => now(),
                'observaciones' => "Pago masivo evento: {$evento->nombre}",
                'observaciones' => "Pago masivo evento: {$evento->nombre}",
                'registrado_por' => $request->user()->miembro ? $request->user()->miembro->id : throw new \Exception('El usuario no tiene un perfil de miembro asociado.'),
            ]);

            $pago->aplicarALiquidaciones([$liquidacion->id]);
            $pagosRealizados[] = $pago;
        }

        return response()->json([
            'success' => true,
            'message' => 'Pagos masivos realizados correctamente',
            'data' => [
                'total_pagos' => count($pagosRealizados),
                'monto_total' => collect($pagosRealizados)->sum('monto'),
            ],
        ], 201);
    }

    /**
     * ═══════════════════════════════════════════════════════════
     * ANULAR PAGO
     * ═══════════════════════════════════════════════════════════
     */
    public function anularPago(Pago $pago): JsonResponse
    {
        if ($pago->estado === Pago::ESTADO_ANULADO) {
            return response()->json([
                'success' => false,
                'message' => 'El pago ya fue anulado anteriormente',
            ], 422);
        }

        $pago->anular();

        return response()->json([
            'success' => true,
            'message' => 'Pago anulado correctamente',
        ]);
    }

    /**
     * ═══════════════════════════════════════════════════════════
     * HISTORIAL DE PAGOS
     * ═══════════════════════════════════════════════════════════
     */
    public function historial(Request $request): JsonResponse
    {
        $query = Pago::with(['miembro', 'registrador'])
            ->procesados();

        if ($request->has('miembro_id')) {
            $query->where('miembro_id', $request->miembro_id);
        }

        if ($request->has('mes') && $request->has('año')) {
            $query->delMes($request->mes, $request->año);
        }

        if ($request->has('fecha_desde')) {
            $query->where('fecha_pago', '>=', $request->fecha_desde);
        }

        if ($request->has('fecha_hasta')) {
            $query->where('fecha_pago', '<=', $request->fecha_hasta);
        }

        $pagos = $query->orderByDesc('fecha_pago')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $pagos,
        ]);
    }

    /**
     * ═══════════════════════════════════════════════════════════
     * RESUMEN DE DEUDAS (Liquidaciones pendientes)
     * ═══════════════════════════════════════════════════════════
     */
    public function resumenDeudas(Request $request): JsonResponse
    {
        $query = Liquidacion::with(['miembro.seccion', 'evento'])
            ->where('estado', '!=', Liquidacion::ESTADO_PAGADO);

        if ($request->has('miembro_id')) {
            $query->where('miembro_id', $request->miembro_id);
        }

        $liquidaciones = $query->orderBy('created_at')->get();

        // Agrupar por miembro
        $porMiembro = $liquidaciones->groupBy('miembro_id')
            ->map(function ($items, $miembroId) {
                $miembro = $items->first()->miembro;
                return [
                    'miembro' => [
                        'id' => $miembro->id,
                        'nombre_completo' => $miembro->nombre_completo,
                        'seccion' => $miembro->seccion->nombre ?? null,
                    ],
                    'total_pendiente' => $items->sum('monto_pendiente'),
                    'cantidad_eventos' => $items->count(),
                    'liquidaciones' => $items,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'total_deuda' => $liquidaciones->sum('monto_pendiente'),
                'total_miembros' => $porMiembro->count(),
                'detalle' => $porMiembro,
            ],
        ]);
    }

    /**
     * ═══════════════════════════════════════════════════════════
     * MI ESTADO DE CUENTA
     * ═══════════════════════════════════════════════════════════
     */
    public function miEstadoCuenta(Request $request): JsonResponse
    {
        $miembro = $request->user()->miembro;

        if (!$miembro) {
             return response()->json(['success' => false, 'message' => 'No tiene un perfil de miembro asociado'], 404);
        }

        $meses = $request->get('meses', 3);

        return response()->json([
            'success' => true,
            'data' => $this->obtenerExtractoMiembro($miembro, $meses),
        ]);
    }

    private function obtenerExtractoMiembro(Miembro $miembro, int $meses): array
    {
        $fechaInicio = now()->subMonths($meses)->startOfMonth();

        $liquidaciones = $miembro->liquidaciones()
            ->with('evento')
            ->whereHas('evento', function ($q) use ($fechaInicio) {
                $q->where('fecha', '>=', $fechaInicio);
            })
            ->orderByDesc('created_at')
            ->get();

        $pagos = $miembro->pagos()
            ->where('fecha_pago', '>=', $fechaInicio)
            ->where('estado', Pago::ESTADO_PROCESADO)
            ->orderByDesc('fecha_pago')
            ->get();

        return [
            'miembro' => [
                'id' => $miembro->id,
                'nombre_completo' => $miembro->nombre_completo,
            ],
            'periodo' => [
                'desde' => $fechaInicio->toDateString(),
                'hasta' => now()->toDateString(),
            ],
            'resumen' => [
                'total_devengado' => $liquidaciones->sum('monto_final'),
                'total_pagado' => $pagos->sum('monto'),
                'saldo_pendiente' => $liquidaciones->sum('monto_final') - $pagos->sum('monto'),
                'total_descuentos' => $liquidaciones->sum('descuento_tardanza'),
            ],
            'eventos' => $liquidaciones->count(),
            'liquidaciones' => $liquidaciones,
            'pagos' => $pagos,
        ];
    }
}
