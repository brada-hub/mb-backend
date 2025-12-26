<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tarifa extends Model
{
    use HasFactory;

    protected $table = 'tarifas';

    protected $fillable = [
        'seccion_id',
        'categoria_id',
        'monto_ensayo',
        'monto_contrato',
    ];

    protected $casts = [
        'monto_ensayo' => 'decimal:2',
        'monto_contrato' => 'decimal:2',
    ];

    // ═══════════════════════════════════════════════════════════
    // RELACIONES
    // ═══════════════════════════════════════════════════════════

    public function seccion(): BelongsTo
    {
        return $this->belongsTo(Seccion::class);
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaSalarial::class, 'categoria_id');
    }

    // ═══════════════════════════════════════════════════════════
    // MÉTODOS ESTÁTICOS
    // ═══════════════════════════════════════════════════════════

    public static function obtenerMonto(int $seccionId, int $categoriaId, string $tipoEvento): float
    {
        $tarifa = self::where('seccion_id', $seccionId)
            ->where('categoria_id', $categoriaId)
            ->first();

        if (!$tarifa) {
            return 0;
        }

        return $tipoEvento === 'contrato'
            ? $tarifa->monto_contrato
            : $tarifa->monto_ensayo;
    }
}
