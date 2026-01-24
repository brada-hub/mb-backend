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

        // Si ya es una URL completa, nos aseguramos de codificar los espacios
        if (str_starts_with($value, 'http')) {
            // Reemplazamos espacios por %20 manualmente para evitar fallos del navegador
            return str_replace(' ', '%20', $value);
        }

        // Si es ruta relativa, asset() se encarga de codificarla correctamente
        return asset(ltrim($value, '/'));
    }

    public function recurso()
    {
        return $this->belongsTo(Recurso::class, 'id_recurso');
    }
}
