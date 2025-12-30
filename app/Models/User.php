<?php

namespace App\Models;

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
        'id_miembro'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'estado' => 'boolean',
        'password' => 'hashed',
    ];

    public function miembro()
    {
        return $this->belongsTo(Miembro::class, 'id_miembro');
    }

    public function dispositivos()
    {
        return $this->hasMany(DispositivoAutorizado::class, 'id_user');
    }
}
