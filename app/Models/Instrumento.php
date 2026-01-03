<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Instrumento extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_instrumento';
    protected $fillable = ['instrumento', 'id_seccion', 'icon_slug'];

    public function seccion()
    {
        return $this->belongsTo(Seccion::class, 'id_seccion');
    }

    public function recursos()
    {
        return $this->hasMany(Recurso::class, 'id_instrumento');
    }

    public function miembros()
    {
        return $this->hasMany(Miembro::class, 'id_instrumento');
    }
}
