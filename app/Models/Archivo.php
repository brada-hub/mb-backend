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

    /**
     * Accesor ultra-agresivo para forzar la ruta correcta en producción.
     * Ignora rutas viejas y subdominios incorrectos.
     */
    public function getUrlArchivoAttribute($value)
    {
        if (!$value) return null;

        // 1. Extraemos solo el nombre real del archivo (el final de la ruta)
        $pathParts = explode('/', str_replace('\\', '/', $value));
        $filename = end($pathParts);

        // 2. Determinamos la carpeta correcta (guias o recursos)
        // Por defecto recursos, a menos que el nombre o la ruta original sugieran que es una guía
        $folder = 'recursos';
        if (stripos($value, 'guias') !== false || stripos($value, 'audio') !== false) {
            $folder = 'guias';
        }

        // 3. Codificamos el nombre del archivo para la web (espacios -> %20, etc.)
        $encodedFilename = rawurlencode($filename);

        // 4. RETORNO FORZADO: No usamos asset() ni rutas relativas.
        // Forzamos el dominio de la API y la carpeta donde moviste los archivos.
        return "https://api.simba.xpertiaplus.com/storage/{$folder}/{$encodedFilename}";
    }

    public function recurso()
    {
        return $this->belongsTo(Recurso::class, 'id_recurso');
    }
}
