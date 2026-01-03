<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Archivo extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_archivo';
    protected $fillable = ['url_archivo', 'tipo', 'nombre_original', 'orden', 'id_recurso'];

    public function recurso()
    {
        return $this->belongsTo(Recurso::class, 'id_recurso');
    }
}
