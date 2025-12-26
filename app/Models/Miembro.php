<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Miembro extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'miembros';

    protected $fillable = [
        'user_id',
        // Datos personales
        'nombres',
        'apellidos',
        'ci_numero',
        'ci_complemento',
        'celular',
        'fecha_nacimiento',
        'foto',
        // Ubicación
        'direccion',
        'latitud',
        'longitud',
        // Referencia
        'referencia_nombre',
        'referencia_celular',
        // Relaciones
        'seccion_id',
        'categoria_id',
        // Estado
        'notas',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'latitud' => 'decimal:8',
        'longitud' => 'decimal:8',
        'celular' => 'integer',
    ];

    protected $appends = ['ci_completo', 'nombre_completo', 'iniciales'];

    // ═══════════════════════════════════════════════════════════
    // RELACIONES
    // ═══════════════════════════════════════════════════════════

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function seccion(): BelongsTo
    {
        return $this->belongsTo(Seccion::class);
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaSalarial::class, 'categoria_id');
    }

    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class);
    }

    public function liquidaciones(): HasMany
    {
        return $this->hasMany(Liquidacion::class);
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }

    public function notificaciones(): HasMany
    {
        return $this->hasMany(Notificacion::class);
    }

    public function eventos(): BelongsToMany
    {
        return $this->belongsToMany(Evento::class, 'evento_miembros')
            ->withPivot(['estado', 'propuesto_por', 'confirmado_por', 'notificado'])
            ->withTimestamps();
    }

    public function partiturasSubidas(): HasMany
    {
        return $this->hasMany(Partitura::class, 'subido_por');
    }

    // ═══════════════════════════════════════════════════════════
    // ACCESSORS
    // ═══════════════════════════════════════════════════════════

    public function getNombreCompletoAttribute(): string
    {
        return "{$this->nombres} {$this->apellidos}";
    }

    public function getCiCompletoAttribute(): string
    {
        return $this->ci_numero . ($this->ci_complemento ? '-'.$this->ci_complemento : '');
    }

    public function getInicialesAttribute(): string
    {
        $nombres = explode(' ', $this->nombres);
        $apellidos = explode(' ', $this->apellidos);

        return strtoupper(
            substr($nombres[0] ?? '', 0, 1) .
            substr($apellidos[0] ?? '', 0, 1)
        );
    }

    // ═══════════════════════════════════════════════════════════
    // MÉTODOS DELEGADOS (ROLES)
    // ═══════════════════════════════════════════════════════════

    public function esSuperAdmin(): bool
    {
        return $this->user?->hasRole(Rol::SUPER_ADMIN) ?? false;
    }

    public function esDirector(): bool
    {
        return $this->user?->hasRole(Rol::DIRECTOR) ?? false;
    }

    public function esJefeSeccion(): bool
    {
        return $this->user?->hasRole(Rol::JEFE_SECCION) ?? false;
    }

    public function tienePermiso(string $modulo, string $accion): bool
    {
        if ($this->esSuperAdmin()) {
            return true;
        }

        // Iterar sobre roles del usuario
        if (!$this->user) return false;

        foreach ($this->user->roles as $rol) {
            if ($rol->tienePermiso($modulo, $accion)) {
                return true;
            }
        }

        return false;
    }

    public function puedeGestionarSeccion(int $seccionId): bool
    {
        if ($this->esSuperAdmin() || $this->esDirector()) {
            return true;
        }

        if ($this->esJefeSeccion() && $this->seccion_id === $seccionId) {
            return true;
        }

        return false;
    }

    // ═══════════════════════════════════════════════════════════
    // SCOPES
    // ═══════════════════════════════════════════════════════════

    public function scopeDeSeccion($query, int $seccionId)
    {
        return $query->where('seccion_id', $seccionId);
    }

    public function scopeDeCategoria($query, int $categoriaId)
    {
        return $query->where('categoria_id', $categoriaId);
    }

    public function scopeActivos($query)
    {
        return $query->whereHas('user', function($q) {
            $q->where('activo', true);
        });
    }
}
