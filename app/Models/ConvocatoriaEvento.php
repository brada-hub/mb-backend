<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConvocatoriaEvento extends Model
{
    use HasFactory;
    protected $table = 'convocatoria_evento';
    protected $primaryKey = 'id_convocatoria';
    protected $fillable = ['id_evento', 'id_miembro', 'confirmado_por_director'];

    public function evento()
    {
        return $this->belongsTo(Evento::class, 'id_evento');
    }

    public function miembro()
    {
        return $this->belongsTo(Miembro::class, 'id_miembro');
    }

    public function asistencia()
    {
        return $this->hasOne(Asistencia::class, 'id_convocatoria');
    }
}
