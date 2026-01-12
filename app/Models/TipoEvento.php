<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoEvento extends Model
{
    use HasFactory;
    protected $table = 'tipos_evento';
    protected $primaryKey = 'id_tipo_evento';
    protected $fillable = ['evento', 'minutos_antes_marcar', 'horas_despues_sellar', 'minutos_tolerancia', 'minutos_cierre'];
}
