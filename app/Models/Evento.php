<?php

namespace App\Models;

use App\Traits\BelongsToBanda;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Evento extends Model
{
    use HasFactory, BelongsToBanda;
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
        'minutos_cierre',
        'asistencia_cerrada',
        'remunerado',
        'monto_sugerido',
        'id_banda'
    ];

    protected $casts = [
        'remunerado' => 'boolean',
        'fecha' => 'date:Y-m-d',
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
