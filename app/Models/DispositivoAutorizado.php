<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DispositivoAutorizado extends Model
{
    use HasFactory;
    protected $table = 'dispositivos_autorizados';
    protected $primaryKey = 'id_dispositivo';
    protected $fillable = [
        'uuid_celular',
        'nombre_modelo',
        'fecha_registro',
        'estado',
        'id_user',
        'fcm_token'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }
}
