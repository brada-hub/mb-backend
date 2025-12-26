<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permiso extends Model
{
    use HasFactory;

    protected $table = 'permisos';

    protected $fillable = [
        'modulo',
        'accion',
        'nombre',
        'descripcion',
    ];

    // ═══════════════════════════════════════════════════════════
    // CONSTANTES DE MÓDULOS
    // ═══════════════════════════════════════════════════════════

    const MODULO_MIEMBROS = 'miembros';
    const MODULO_EVENTOS = 'eventos';
    const MODULO_ASISTENCIAS = 'asistencias';
    const MODULO_PARTITURAS = 'partituras';
    const MODULO_PAGOS = 'pagos';
    const MODULO_REPORTES = 'reportes';
    const MODULO_CONFIGURACION = 'configuracion';

    // ═══════════════════════════════════════════════════════════
    // CONSTANTES DE ACCIONES
    // ═══════════════════════════════════════════════════════════

    const ACCION_VER = 'ver';
    const ACCION_CREAR = 'crear';
    const ACCION_EDITAR = 'editar';
    const ACCION_ELIMINAR = 'eliminar';

    // ═══════════════════════════════════════════════════════════
    // RELACIONES
    // ═══════════════════════════════════════════════════════════

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'rol_permiso');
    }

    // ═══════════════════════════════════════════════════════════
    // ACCESSORS
    // ═══════════════════════════════════════════════════════════

    public function getIdentificadorAttribute(): string
    {
        return "{$this->modulo}.{$this->accion}";
    }
}
