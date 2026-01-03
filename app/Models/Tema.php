<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tema extends Model
{
    use HasFactory;
    protected $table = 'temas';
    protected $primaryKey = 'id_tema';
    protected $fillable = ['id_genero', 'nombre_tema'];

    public function genero()
    {
        return $this->belongsTo(Genero::class, 'id_genero');
    }

    public function recursos()
    {
        return $this->hasMany(Recurso::class, 'id_tema');
    }

    public function videos()
    {
        return $this->hasMany(Video::class, 'id_tema');
    }
}
