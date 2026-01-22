<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Traits\BelongsToBanda;

class Seccion extends Model
{
    use HasFactory, BelongsToBanda;
    protected $table = 'secciones';
    protected $primaryKey = 'id_seccion';
    protected $fillable = ['seccion', 'descripcion', 'estado', 'id_banda'];
    protected $casts = ['estado' => 'boolean'];

    public function miembros()
    {
        return $this->hasMany(Miembro::class, 'id_seccion', 'id_seccion');
    }

    public function instrumentos()
    {
        return $this->hasMany(Instrumento::class, 'id_seccion');
    }
}
