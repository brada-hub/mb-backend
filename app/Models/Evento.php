<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Evento extends Model
{
    use HasFactory;
    protected $table = 'eventos';
    protected $primaryKey = 'id_evento';
    protected $fillable = [
        'evento',
        'fecha',
        'hora',
        'latitud',
        'longitud',
        'direccion',
        'radio',
        'estado',
        'id_tipo_evento',
        'ingreso_total_contrato',
        'presupuesto_limite_sueldos',
        'version_evento' // Added for offline check
    ];

    public function tipo()
    {
        return $this->belongsTo(TipoEvento::class, 'id_tipo_evento');
    }

    public function asistencias()
    {
        return $this->hasMany(Asistencia::class, 'id_evento');
    }

    public function convocatorias()
    {
        return $this->hasMany(ConvocatoriaEvento::class, 'id_evento');
    }
}
