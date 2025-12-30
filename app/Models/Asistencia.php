<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Asistencia extends Model
{
    use HasFactory;
    protected $table = 'asistencias';
    protected $primaryKey = 'id_asistencia';
    protected $fillable = [
        'id_evento',
        'id_miembro',
        'hora_llegada',
        'minutos_retraso',
        'estado',
        'offline_uuid',
        'latitud_marcado',
        'longitud_marcado',
        'fecha_sincronizacion'
    ];

    public function evento()
    {
        return $this->belongsTo(Evento::class, 'id_evento');
    }

    public function miembro()
    {
        return $this->belongsTo(Miembro::class, 'id_miembro');
    }
}
