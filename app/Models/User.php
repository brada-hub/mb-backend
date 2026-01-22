<?php

namespace App\Models;

use App\Traits\BelongsToBanda;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $primaryKey = 'id_user';

    protected $fillable = [
        'user',
        'password',
        'token',
        'estado',
        'is_super_admin',
        'id_miembro',
        'password_changed',
        'profile_completed',
        'limite_dispositivos',
        'preferencias_notificaciones',
        'fcm_token',
        'theme_preference',
        'id_banda'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'estado' => 'boolean',
        'is_super_admin' => 'boolean',
        'password_changed' => 'boolean',
        'profile_completed' => 'boolean',
        'password' => 'hashed',
        'preferencias_notificaciones' => 'array'
    ];

    public function miembro()
    {
        return $this->belongsTo(Miembro::class, 'id_miembro');
    }

    public function dispositivos()
    {
        return $this->hasMany(DispositivoAutorizado::class, 'id_user');
    }

    public function banda()
    {
        return $this->belongsTo(Banda::class, 'id_banda');
    }

    /**
     * Verifica si el usuario es Super Admin (acceso global a todas las bandas)
     */
    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin === true;
    }
}
