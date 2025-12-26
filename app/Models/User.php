<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'username',
        'password',
        'device_id',
        'device_nombre',
        'multi_login',
        'activo',
        'cambio_password_requerido'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'activo' => 'boolean',
            'multi_login' => 'boolean',
            'cambio_password_requerido' => 'boolean',
        ];
    }

    public function miembro()
    {
        return $this->hasOne(Miembro::class);
    }

    public function roles()
    {
        return $this->belongsToMany(Rol::class, 'user_roles', 'user_id', 'role_id');
    }

    // Helpers
    public function hasRole($slug)
    {
        return $this->roles()->where('slug', $slug)->exists();
    }

    public function verificarDispositivo(?string $deviceId): bool
    {
        // Si tiene multi-login habilitado, siempre puede
        if ($this->multi_login) {
            return true;
        }

        // Si no tiene device_id registrado, es el primer login
        if (empty($this->device_id)) {
            return true;
        }

        return $this->device_id === $deviceId;
    }

    public function registrarDispositivo(string $deviceId, ?string $deviceNombre = null): void
    {
        $this->update([
            'device_id' => $deviceId,
            'device_nombre' => $deviceNombre,
        ]);
        // timestamps se actualizan solos
    }
}
