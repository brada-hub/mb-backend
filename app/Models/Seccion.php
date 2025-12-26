<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Seccion extends Model
{
    use HasFactory;

    protected $table = 'secciones';

    protected $fillable = [
        'nombre',
        'nombre_corto',
        'icono',
        'color',
        'descripcion',
        'es_viento',
        'orden',
        'activo',
    ];

    protected $casts = [
        'es_viento' => 'boolean',
        'activo' => 'boolean',
    ];

    // ═══════════════════════════════════════════════════════════
    // RELACIONES
    // ═══════════════════════════════════════════════════════════

    public function miembros(): HasMany
    {
        return $this->hasMany(Miembro::class);
    }

    public function tarifas(): HasMany
    {
        return $this->hasMany(Tarifa::class);
    }

    public function partituras(): HasMany
    {
        return $this->hasMany(Partitura::class);
    }

    public function eventoCupos(): HasMany
    {
        return $this->hasMany(EventoCupo::class);
    }

    // ═══════════════════════════════════════════════════════════
    // SCOPES
    // ═══════════════════════════════════════════════════════════

    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    public function scopeViento($query)
    {
        return $query->where('es_viento', true);
    }

    public function scopeOrdenadas($query)
    {
        return $query->orderBy('orden')->orderBy('nombre');
    }

    // ═══════════════════════════════════════════════════════════
    // ACCESSORS
    // ═══════════════════════════════════════════════════════════

    public function getTotalMiembrosAttribute(): int
    {
        return $this->miembros()->whereHas('user', function($q) {
            $q->where('activo', true);
        })->count();
    }
}
