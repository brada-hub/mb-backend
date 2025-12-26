<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Genero extends Model
{
    use HasFactory;

    protected $table = 'generos';

    protected $fillable = [
        'nombre',
        'slug',
        'descripcion',
        'icono',
        'color',
        'orden',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($genero) {
            if (empty($genero->slug)) {
                $genero->slug = Str::slug($genero->nombre);
            }
        });
    }

    // ═══════════════════════════════════════════════════════════
    // RELACIONES
    // ═══════════════════════════════════════════════════════════

    public function temas(): HasMany
    {
        return $this->hasMany(Tema::class);
    }

    // ═══════════════════════════════════════════════════════════
    // ACCESSORS
    // ═══════════════════════════════════════════════════════════

    public function getTotalTemasAttribute(): int
    {
        return $this->temas()->where('activo', true)->count();
    }

    // ═══════════════════════════════════════════════════════════
    // SCOPES
    // ═══════════════════════════════════════════════════════════

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeOrdenados($query)
    {
        return $query->orderBy('orden')->orderBy('nombre');
    }
}
