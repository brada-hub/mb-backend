<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Liquidacion extends Model
{
    use HasFactory;

    protected $table = 'liquidaciones';

    protected $fillable = [
        'evento_id',
        'miembro_id',
        'monto_base',
        'descuento_tardanza',
        'otros_descuentos',
        'bonificacion',
        'monto_final',
        'estado',
        'observaciones',
    ];

    protected $casts = [
        'monto_base' => 'decimal:2',
        'descuento_tardanza' => 'decimal:2',
        'otros_descuentos' => 'decimal:2',
        'bonificacion' => 'decimal:2',
        'monto_final' => 'decimal:2',
    ];

    // ═══════════════════════════════════════════════════════════
    // CONSTANTES
    // ═══════════════════════════════════════════════════════════

    const ESTADO_PENDIENTE = 'pendiente';
    const ESTADO_PAGADO = 'pagado';
    const ESTADO_PARCIAL = 'parcial';

    // ═══════════════════════════════════════════════════════════
    // RELACIONES
    // ═══════════════════════════════════════════════════════════

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class);
    }

    public function miembro(): BelongsTo
    {
        return $this->belongsTo(Miembro::class);
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(PagoLiquidacion::class);
    }

    // ═══════════════════════════════════════════════════════════
    // MÉTODOS
    // ═══════════════════════════════════════════════════════════

    public function calcularMontoFinal(): void
    {
        $this->monto_final = $this->monto_base
            - $this->descuento_tardanza
            - $this->otros_descuentos
            + $this->bonificacion;

        $this->save();
    }

    public static function generarParaEvento(Evento $evento): void
    {
        $miembrosConfirmados = $evento->miembros()
            ->wherePivot('estado', 'confirmado')
            ->get();

        foreach ($miembrosConfirmados as $miembro) {
            // Obtener tarifa
            $montoBase = Tarifa::obtenerMonto(
                $miembro->seccion_id,
                $miembro->categoria_id,
                $evento->tipo
            );

            // Obtener descuento por tardanza
            $asistencia = Asistencia::where('evento_id', $evento->id)
                ->where('miembro_id', $miembro->id)
                ->first();

            $descuentoTardanza = $asistencia?->descuento ?? 0;

            // Crear liquidación
            $liquidacion = self::updateOrCreate(
                [
                    'evento_id' => $evento->id,
                    'miembro_id' => $miembro->id,
                ],
                [
                    'monto_base' => $montoBase,
                    'descuento_tardanza' => $descuentoTardanza,
                    'otros_descuentos' => 0,
                    'bonificacion' => 0,
                    'estado' => self::ESTADO_PENDIENTE,
                ]
            );

            $liquidacion->calcularMontoFinal();
        }
    }

    // ═══════════════════════════════════════════════════════════
    // ACCESSORS
    // ═══════════════════════════════════════════════════════════

    public function getTotalDescuentosAttribute(): float
    {
        return $this->descuento_tardanza + $this->otros_descuentos;
    }

    public function getMontoPagadoAttribute(): float
    {
        return $this->pagos()->sum('monto_aplicado');
    }

    public function getMontoPendienteAttribute(): float
    {
        return max(0, $this->monto_final - $this->monto_pagado);
    }

    // ═══════════════════════════════════════════════════════════
    // SCOPES
    // ═══════════════════════════════════════════════════════════

    public function scopePendientes($query)
    {
        return $query->where('estado', self::ESTADO_PENDIENTE);
    }

    public function scopePagadas($query)
    {
        return $query->where('estado', self::ESTADO_PAGADO);
    }

    public function scopeDeMiembro($query, int $miembroId)
    {
        return $query->where('miembro_id', $miembroId);
    }

    public function scopeDelMes($query, int $mes, int $año)
    {
        return $query->whereHas('evento', function ($q) use ($mes, $año) {
            $q->whereMonth('fecha', $mes)->whereYear('fecha', $año);
        });
    }
}
