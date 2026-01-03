<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VozInstrumental extends Model
{
    use HasFactory;
    protected $table = 'voces_instrumentales';
    protected $primaryKey = 'id_voz';
    protected $fillable = ['nombre_voz'];

    public function recursos()
    {
        return $this->hasMany(RecursoMultimedia::class, 'id_voz');
    }
}
