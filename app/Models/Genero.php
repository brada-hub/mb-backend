<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Traits\BelongsToBanda;

class Genero extends Model
{
    use HasFactory, BelongsToBanda;
    protected $table = 'generos';
    protected $primaryKey = 'id_genero';
    protected $fillable = ['nombre_genero', 'banner_opcional', 'color_primario', 'color_secundario', 'orden', 'id_banda'];
    protected $appends = ['banner_url'];

    public function getBannerUrlAttribute()
    {
        if (!$this->banner_opcional) return null;

        if (str_starts_with($this->banner_opcional, 'http')) {
            return str_replace(' ', '%20', $this->banner_opcional);
        }

        return asset('storage/' . $this->banner_opcional);
    }

    public function temas()
    {
        return $this->hasMany(Tema::class, 'id_genero');
    }
}
