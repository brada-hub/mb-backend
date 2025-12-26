<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Rol extends Model
{
    use HasFactory;

    protected $table = 'roles';

    protected $fillable = [
        'nombre',
        'slug',
        'descripcion',
        'nivel',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    // ═══════════════════════════════════════════════════════════
    // CONSTANTES DE ROLES
    // ═══════════════════════════════════════════════════════════

    const SUPER_ADMIN = 'super_admin';
    const DIRECTOR = 'director';
    const JEFE_SECCION = 'jefe_seccion';
    const MIEMBRO = 'miembro';

    // ═══════════════════════════════════════════════════════════
    // RELACIONES
    // ═══════════════════════════════════════════════════════════

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles', 'role_id', 'user_id');
    }

    public function permisos(): BelongsToMany
    {
        return $this->belongsToMany(Permiso::class, 'rol_permiso');
    }

    // ═══════════════════════════════════════════════════════════
    // MÉTODOS
    // ═══════════════════════════════════════════════════════════

    public function tienePermiso(string $modulo, string $accion): bool
    {
        return $this->permisos()
            ->where('modulo', $modulo)
            ->where('accion', $accion)
            ->exists();
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
        return $query->orderByDesc('nivel');
    }
}
