<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Banda extends Model
{
    use HasFactory;

    protected $table = 'bandas';
    protected $primaryKey = 'id_banda';

    protected $fillable = [
        'nombre',
        'slug',
        'logo',
        'color_primario',
        'color_secundario',
        'estado',
        'plan',
        'fecha_vencimiento',
        'max_miembros',
        'notificaciones_habilitadas',
        'cuota_mensual'
    ];

    protected $casts = [
        'estado' => 'boolean',
        'notificaciones_habilitadas' => 'boolean',
        'fecha_vencimiento' => 'date',
        'cuota_mensual' => 'float',
        'max_miembros' => 'integer'
    ];

    public function miembros()
    {
        return $this->hasMany(Miembro::class, 'id_banda');
    }

    public function eventos()
    {
        return $this->hasMany(Evento::class, 'id_banda');
    }

    public function roles()
    {
        return $this->hasMany(Rol::class, 'id_banda');
    }

    /**
     * Verifica si la banda tiene su suscripción activa
     */
    public function isActiva(): bool
    {
        return $this->estado && ($this->fecha_vencimiento === null || $this->fecha_vencimiento->isFuture());
    }

    /**
     * Verifica si se ha alcanzado el límite de miembros según el plan
     */
    public function haAlcanzadoLimiteMiembros(): bool
    {
        return $this->miembros()->count() >= $this->max_miembros;
    }
}
