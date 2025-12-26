<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoriaSalarial extends Model
{
    use HasFactory;

    protected $table = 'categorias_salariales';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'monto_base',
        'orden',
        'activo',
    ];

    protected $casts = [
        'monto_base' => 'decimal:2',
        'activo' => 'boolean',
    ];

    // ═══════════════════════════════════════════════════════════
    // RELACIONES
    // ═══════════════════════════════════════════════════════════

    public function miembros(): HasMany
    {
        return $this->hasMany(Miembro::class, 'categoria_id');
    }

    public function tarifas(): HasMany
    {
        return $this->hasMany(Tarifa::class, 'categoria_id');
    }

    // ═══════════════════════════════════════════════════════════
    // SCOPES
    // ═══════════════════════════════════════════════════════════

    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    public function scopeOrdenadas($query)
    {
        return $query->orderBy('orden')->orderBy('codigo');
    }
}
