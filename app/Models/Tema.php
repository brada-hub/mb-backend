<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Traits\BelongsToBanda;

class Tema extends Model
{
    use HasFactory, BelongsToBanda;
    protected $table = 'temas';
    protected $primaryKey = 'id_tema';
    protected $fillable = ['id_genero', 'nombre_tema', 'id_banda'];

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

    public function audio()
    {
        return $this->morphOne(Audio::class, 'entidad', 'tipo_entidad', 'id_entidad');
    }

    public function mixes()
    {
        return $this->belongsToMany(Mix::class, 'detalle_mixes', 'id_tema', 'id_mix')
                    ->withPivot('id_detalle_mix', 'orden')
                    ->withTimestamps();
    }
}
