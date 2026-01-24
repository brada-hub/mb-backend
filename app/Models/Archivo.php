<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Archivo extends Model
{
    use HasFactory, Auditable;

    protected $primaryKey = 'id_archivo';
    protected $fillable = ['url_archivo', 'tipo', 'nombre_original', 'orden', 'id_recurso'];

    public function getUrlArchivoAttribute($value)
    {
        if (!$value) return null;
        if (str_starts_with($value, 'http')) return $value;
        return asset($value);
    }

    public function recurso()
    {
        return $this->belongsTo(Recurso::class, 'id_recurso');
    }
}
