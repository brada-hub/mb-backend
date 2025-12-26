<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pago extends Model
{
    use HasFactory;

    protected $table = 'pagos';

    protected $fillable = [
        'miembro_id',
        'monto',
        'metodo',
        'referencia',
        'fecha_pago',
        'periodo_inicio',
        'periodo_fin',
        'estado',
        'observaciones',
        'registrado_por',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'fecha_pago' => 'date',
        'periodo_inicio' => 'date',
        'periodo_fin' => 'date',
    ];

    // ═══════════════════════════════════════════════════════════
    // CONSTANTES
    // ═══════════════════════════════════════════════════════════

    const METODO_EFECTIVO = 'efectivo';
    const METODO_TRANSFERENCIA = 'transferencia';
    const METODO_QR = 'qr';

    const ESTADO_PROCESADO = 'procesado';
    const ESTADO_ANULADO = 'anulado';

    // ═══════════════════════════════════════════════════════════
    // RELACIONES
    // ═══════════════════════════════════════════════════════════

    public function miembro(): BelongsTo
    {
        return $this->belongsTo(Miembro::class);
    }

    public function registrador(): BelongsTo
    {
        return $this->belongsTo(Miembro::class, 'registrado_por');
    }

    public function liquidaciones(): HasMany
    {
        return $this->hasMany(PagoLiquidacion::class);
    }

    // ═══════════════════════════════════════════════════════════
    // MÉTODOS
    // ═══════════════════════════════════════════════════════════

    /**
     * Aplica el pago a las liquidaciones pendientes del miembro
     */
    public function aplicarALiquidaciones(array $liquidacionIds = []): void
    {
        $montoRestante = $this->monto;

        // Si no se especifican IDs, obtener todas las pendientes
        $query = Liquidacion::where('miembro_id', $this->miembro_id)
            ->where('estado', '!=', Liquidacion::ESTADO_PAGADO)
            ->orderBy('created_at');

        if (!empty($liquidacionIds)) {
            $query->whereIn('id', $liquidacionIds);
        }

        $liquidaciones = $query->get();

        foreach ($liquidaciones as $liquidacion) {
            if ($montoRestante <= 0) break;

            $montoPendiente = $liquidacion->monto_pendiente;
            $montoAplicar = min($montoRestante, $montoPendiente);

            // Crear registro de pago-liquidación
            PagoLiquidacion::create([
                'pago_id' => $this->id,
                'liquidacion_id' => $liquidacion->id,
                'monto_aplicado' => $montoAplicar,
            ]);

            // Actualizar estado de liquidación
            if ($montoAplicar >= $montoPendiente) {
                $liquidacion->update(['estado' => Liquidacion::ESTADO_PAGADO]);
            } else {
                $liquidacion->update(['estado' => Liquidacion::ESTADO_PARCIAL]);
            }

            $montoRestante -= $montoAplicar;
        }
    }

    /**
     * Anula el pago y revierte las liquidaciones afectadas
     */
    public function anular(): void
    {
        // Revertir liquidaciones a pendiente
        foreach ($this->liquidaciones as $pagoLiquidacion) {
            $liquidacion = $pagoLiquidacion->liquidacion;

            // Si no hay otros pagos, volver a pendiente
            $otrosPagos = $liquidacion->pagos()
                ->where('pago_id', '!=', $this->id)
                ->sum('monto_aplicado');

            if ($otrosPagos <= 0) {
                $liquidacion->update(['estado' => Liquidacion::ESTADO_PENDIENTE]);
            } elseif ($otrosPagos < $liquidacion->monto_final) {
                $liquidacion->update(['estado' => Liquidacion::ESTADO_PARCIAL]);
            }
        }

        // Eliminar registros de pago-liquidación
        $this->liquidaciones()->delete();

        // Marcar pago como anulado
        $this->update(['estado' => self::ESTADO_ANULADO]);
    }

    // ═══════════════════════════════════════════════════════════
    // ACCESSORS
    // ═══════════════════════════════════════════════════════════

    public function getMetodoTextoAttribute(): string
    {
        return match($this->metodo) {
            self::METODO_EFECTIVO => 'Efectivo',
            self::METODO_TRANSFERENCIA => 'Transferencia',
            self::METODO_QR => 'QR',
            default => 'Desconocido',
        };
    }

    // ═══════════════════════════════════════════════════════════
    // SCOPES
    // ═══════════════════════════════════════════════════════════

    public function scopeProcesados($query)
    {
        return $query->where('estado', self::ESTADO_PROCESADO);
    }

    public function scopeDeMiembro($query, int $miembroId)
    {
        return $query->where('miembro_id', $miembroId);
    }

    public function scopeDelMes($query, int $mes, int $año)
    {
        return $query->whereMonth('fecha_pago', $mes)
            ->whereYear('fecha_pago', $año);
    }
}
