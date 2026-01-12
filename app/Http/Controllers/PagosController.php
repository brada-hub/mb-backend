<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ConvocatoriaEvento;
use App\Models\Miembro;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PagosController extends Controller
{
    /**
     * ADMIN: Obtener resumen de deudas agrupado por músico.
     * Solo considera eventos CONTRATO o BANDIN, donde la asistencia sea válida (PRESENTE/PUNTUAL/RETRASO)
     * y NO esté pagado.
     */
    public function resumenDeudas()
    {
        // 1. Obtener todas las convocatorias pendientes de pago que cumplan las reglas
        // Reglas: Evento (CONTRATO|BANDIN), Asistencia (PRESENTE|PUNTUAL|RETRASO), Pagado (FALSE)

        $deudas = ConvocatoriaEvento::with(['miembro.instrumento', 'evento.tipo'])
            ->whereHas('evento', function($q) {
                $q->where('remunerado', true);
            })
            ->whereHas('asistencia', function($q) {
                $q->whereIn('estado', ['PRESENTE', 'PUNTUAL', 'RETRASO']);
            })
            ->where('pagado', false)
            ->get();

        // 2. Agrupar por miembro
        $agrupado = $deudas->groupBy('id_miembro')->map(function ($items, $id_miembro) {
            $miembro = $items->first()->miembro;

            return [
                'id_miembro' => $miembro->id_miembro,
                'nombres' => $miembro->nombres,
                'apellidos' => $miembro->apellidos,
                'instrumento' => $miembro->instrumento->instrumento ?? 'N/A',
                'foto_url' => $miembro->foto_url, // Si existe
                'total_eventos' => $items->count(),
                'detalle_ids' => $items->pluck('id_convocatoria'), // Para acciones rápidas
                'eventos_list' => $items->map(function($i) {
                    return [
                        'fecha' => $i->evento->fecha,
                        'nombre' => $i->evento->evento,
                        'tipo' => $i->evento->tipo->evento
                    ];
                })->sortBy('fecha')->values()
            ];
        })->values();

        return response()->json($agrupado);
    }

    /**
     * ADMIN/MIEMBRO: Detalle de deuda de un miembro específico
     */
    public function detalleDeuda($id_miembro)
    {
        $detalles = ConvocatoriaEvento::with(['evento.tipo', 'asistencia'])
            ->where('id_miembro', $id_miembro)
            ->whereHas('evento', function($q) {
                $q->where('remunerado', true);
            })
            ->whereHas('asistencia', function($q) {
                $q->whereIn('estado', ['PRESENTE', 'PUNTUAL', 'RETRASO']);
            })
            ->where('pagado', false)
            ->orderBy(DB::raw('(SELECT fecha FROM eventos WHERE eventos.id_evento = convocatoria_evento.id_evento)')) // Ordenar por fecha ev
            ->get()
            ->map(function($c) {
                return [
                    'id_convocatoria' => $c->id_convocatoria,
                    'evento' => $c->evento->evento,
                    'tipo' => $c->evento->tipo->evento,
                    'fecha' => $c->evento->fecha,
                    'hora' => $c->evento->hora,
                    'estado_asistencia' => $c->asistencia->estado
                ];
            });

        return response()->json($detalles);
    }

    /**
     * ADMIN: Marcar como pagados 1 o varios items
     */
    public function pagar(Request $request)
    {
        $request->validate([
            'id_convocatorias' => 'required|array',
            'id_convocatorias.*' => 'exists:convocatoria_evento,id_convocatoria'
        ]);

        $convocatorias = ConvocatoriaEvento::with('miembro.user')
            ->whereIn('id_convocatoria', $request->id_convocatorias)
            ->get();

        foreach ($convocatorias as $conv) {
            $conv->update([
                'pagado' => true,
                'fecha_pago' => Carbon::now('America/La_Paz')
            ]);
        }

        // Notificar a los músicos
        $agrupadoPorMiembro = $convocatorias->groupBy('id_miembro');
        foreach ($agrupadoPorMiembro as $id_miembro => $items) {
            $miembro = $items->first()->miembro;
            if ($miembro && $miembro->user) {
                $cantidad = $items->count();
                $mensaje = $cantidad === 1
                    ? "Se ha registrado el pago de 1 evento: {$items->first()->evento->evento} 💰"
                    : "Se han registrado pagos para {$cantidad} eventos. Revisa tu historial de cobros 💰";

                \App\Models\Notificacion::enviar(
                    $miembro->user->id_user,
                    "¡Pago Confirmado!",
                    $mensaje,
                    $id_miembro,
                    'pago',
                    '/dashboard/mis-pagos'
                );
            }
        }

        return response()->json(['message' => 'Pagos registrados exitosamente']);
    }

    /**
     * ADMIN: Generar PDF de Planilla de Pagos
     */
    public function generarReportePDF()
    {
        // Reutilizamos la lógica de resumenDeudas para obtener los datos
        $deudas = $this->resumenDeudas()->original;

        // Filtramos solo los que tienen deudas (aunque la query original ya lo hace implícitamente al buscar 'pagado' false,
        // pero aseguramos que haya al menos 1 por si acaso la lógica cambia)
        $deudores = collect($deudas)->filter(function($d) {
            return $d['total_eventos'] > 0;
        });

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.reporte_deudas', ['deudores' => $deudores]);

        return $pdf->download('planilla_deudas_' . date('Y-m-d') . '.pdf');
    }

    /**
     * MIEMBRO: Mi historial de pagos (Pagados y Pendientes)
     */
    public function miHistorial(Request $request)
    {
        $user = $request->user();
        $id_miembro = $user->miembro->id_miembro;

        // Pendientes
        $pendientes = $this->detalleDeuda($id_miembro)->original; // Reusamos lógica

        // Pagados (Histórico)
        $pagados = ConvocatoriaEvento::with(['evento.tipo'])
            ->where('id_miembro', $id_miembro)
            ->whereHas('evento', function($q) {
                $q->where('remunerado', true);
            })
            ->where('pagado', true)
            ->orderBy('fecha_pago', 'desc')
            ->limit(50) // Limite razonable
            ->get()
            ->map(function($c) {
                return [
                    'id_convocatoria' => $c->id_convocatoria,
                    'evento' => $c->evento->evento,
                    'tipo' => $c->evento->tipo->evento,
                    'fecha_evento' => $c->evento->fecha,
                    'fecha_pago' => $c->fecha_pago
                ];
            });

        return response()->json([
            'por_cobrar' => $pendientes,
            'historial_cobrado' => $pagados
        ]);
    }
}
