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
}
