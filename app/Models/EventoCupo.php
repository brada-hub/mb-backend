<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventoCupo extends Model
{
    use HasFactory;

    protected $table = 'evento_cupos';

    protected $fillable = [
        'evento_id',
        'seccion_id',
        'cantidad',
    ];

    // ═══════════════════════════════════════════════════════════
    // RELACIONES
    // ═══════════════════════════════════════════════════════════

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class);
    }

    public function seccion(): BelongsTo
    {
        return $this->belongsTo(Seccion::class);
    }

    // ═══════════════════════════════════════════════════════════
    // ACCESSORS
    // ═══════════════════════════════════════════════════════════

    public function getMiembrosAsignadosAttribute(): int
    {
        return $this->evento->miembros()
            ->wherePivot('seccion_id', $this->seccion_id)
            ->count();
    }

    public function getCuposDisponiblesAttribute(): int
    {
        return max(0, $this->cantidad - $this->miembros_asignados);
    }
}
