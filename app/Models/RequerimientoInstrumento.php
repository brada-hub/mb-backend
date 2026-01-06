<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequerimientoInstrumento extends Model
{
    use HasFactory;

    protected $table = 'requerimiento_instrumento';
    protected $primaryKey = 'id_requerimiento';
    protected $fillable = [
        'id_evento',
        'id_instrumento',
        'cantidad_necesaria',
    ];

    public function evento()
    {
        return $this->belongsTo(Evento::class, 'id_evento');
    }

    public function instrumento()
    {
        return $this->belongsTo(Instrumento::class, 'id_instrumento');
    }
}
