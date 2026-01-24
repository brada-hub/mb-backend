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

        // 2. Quitamos slashes iniciales
        $path = ltrim($path, '/');

        // 3. Si no tiene 'storage/', se lo ponemos
        if (!str_starts_with($path, 'storage/')) {
            $path = 'storage/' . $path;
        }

        // 4. Codificamos el nombre del archivo para que el navegador lo entienda
        $parts = explode('/', $path);
        $filename = array_pop($parts);
        $cleanPath = implode('/', $parts) . '/' . rawurlencode($filename);

        return asset($cleanPath);
    }

    public function recurso()
    {
        return $this->belongsTo(Recurso::class, 'id_recurso');
    }
}
