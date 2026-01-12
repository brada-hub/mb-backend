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
        'version_evento',
        'minutos_tolerancia',
        'minutos_tolerancia',
        'minutos_cierre',
        'asistencia_cerrada',
        'remunerado',
        'monto_sugerido'
    ];

    protected $casts = [
        'remunerado' => 'boolean',
        'fecha' => 'date',
        'asistencia_cerrada' => 'boolean'
    ];

    public function tipo()
    {
        return $this->belongsTo(TipoEvento::class, 'id_tipo_evento');
    }

    public function asistencias()
    {
        return $this->hasManyThrough(Asistencia::class, ConvocatoriaEvento::class, 'id_evento', 'id_convocatoria', 'id_evento', 'id_convocatoria');
    }

    public function convocatorias()
    {
        return $this->hasMany(ConvocatoriaEvento::class, 'id_evento');
    }

    public function requerimientos()
    {
        return $this->hasMany(RequerimientoInstrumento::class, 'id_evento');
    }
}
