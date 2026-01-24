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

        $path = $value;

        // 1. Si ya es una URL completa
        if (str_starts_with($path, 'http')) {
            return str_replace(' ', '%20', $path);
        }

        // 2. Limpiamos el path
        $path = ltrim($path, '/');

        // Quitamos el prefijo storage/ si ya lo tiene para no duplicarlo luego
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, 8);
        }

        // 3. Codificamos el nombre del archivo (para espacios, acentos, eñes)
        $parts = explode('/', $path);
        $filename = array_pop($parts);
        $cleanPath = implode('/', $parts) . '/' . rawurlencode($filename);

        // 4. Forzamos el dominio de la API de producción
        return 'https://api.simba.xpertiaplus.com/storage/' . ltrim($cleanPath, '/');
    }

    public function recurso()
    {
        return $this->belongsTo(Recurso::class, 'id_recurso');
    }
}
