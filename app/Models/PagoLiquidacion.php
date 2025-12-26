<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagoLiquidacion extends Model
{
    use HasFactory;

    protected $table = 'pago_liquidaciones';

    protected $fillable = [
        'pago_id',
        'liquidacion_id',
        'monto_aplicado',
    ];

    protected $casts = [
        'monto_aplicado' => 'decimal:2',
    ];

    // ═══════════════════════════════════════════════════════════
    // RELACIONES
    // ═══════════════════════════════════════════════════════════

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class);
    }

    public function liquidacion(): BelongsTo
    {
        return $this->belongsTo(Liquidacion::class);
    }
}
