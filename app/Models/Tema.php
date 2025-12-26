<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Tema extends Model
{
    use HasFactory;

    protected $table = 'temas';

    protected $fillable = [
        'genero_id',
        'nombre',
        'slug',
        'descripcion',
        'compositor',
        'duracion_segundos',
        'orden',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tema) {
            if (empty($tema->slug)) {
                $tema->slug = Str::slug($tema->nombre);
            }
        });
    }

    // ═══════════════════════════════════════════════════════════
    // RELACIONES
    // ═══════════════════════════════════════════════════════════

    public function genero(): BelongsTo
    {
        return $this->belongsTo(Genero::class);
    }

    public function partituras(): HasMany
    {
        return $this->hasMany(Partitura::class);
    }

    // ═══════════════════════════════════════════════════════════
    // ACCESSORS
    // ═══════════════════════════════════════════════════════════

    public function getDuracionFormateadaAttribute(): string
    {
        if (!$this->duracion_segundos) {
            return '--:--';
        }

        $minutos = floor($this->duracion_segundos / 60);
        $segundos = $this->duracion_segundos % 60;

        return sprintf('%02d:%02d', $minutos, $segundos);
    }

    public function getTotalPartiturasAttribute(): int
    {
        return $this->partituras()->where('activo', true)->count();
    }

    // ═══════════════════════════════════════════════════════════
    // MÉTODOS
    // ═══════════════════════════════════════════════════════════

    public function getPartituraParaSeccion(int $seccionId): ?Partitura
    {
        return $this->partituras()
            ->where('seccion_id', $seccionId)
            ->where('activo', true)
            ->first();
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

    public function scopeDeGenero($query, int $generoId)
    {
        return $query->where('genero_id', $generoId);
    }
}
