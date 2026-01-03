<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Recurso extends Model
{
    use HasFactory;

    protected $table = 'recursos';
    protected $primaryKey = 'id_recurso';

    // id_instrumento reemplaza a id_seccion
    // id_tema, id_voz se mantienen
    protected $fillable = [
        'id_instrumento',
        'id_tema',
        'id_voz'
    ];

    public function instrumento()
    {
        return $this->belongsTo(Instrumento::class, 'id_instrumento');
    }

    public function tema()
    {
        return $this->belongsTo(Tema::class, 'id_tema');
    }

    public function voz()
    {
        return $this->belongsTo(VozInstrumental::class, 'id_voz');
    }

    public function archivos()
    {
        return $this->hasMany(Archivo::class, 'id_recurso')->orderBy('orden');
    }

    // Accessors for backward compatibility or easy access
    protected $appends = ['seccion_nombre', 'id_seccion'];

    public function getIdSeccionAttribute()
    {
        return $this->instrumento->id_seccion ?? null;
    }

    public function getSeccionNombreAttribute()
    {
        return $this->instrumento->seccion->seccion ?? null;
    }
}
