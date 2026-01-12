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
        'id_convocatoria',
        'hora_llegada',
        'minutos_retraso',
        'estado',
        'offline_uuid',
        'latitud_marcado',
        'longitud_marcado',
        'fecha_sincronizacion',
        'observacion'
    ];

    public function convocatoria()
    {
        return $this->belongsTo(ConvocatoriaEvento::class, 'id_convocatoria');
    }
}
