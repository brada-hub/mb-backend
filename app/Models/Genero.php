<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Genero extends Model
{
    use HasFactory;
    protected $table = 'generos';
    protected $primaryKey = 'id_genero';
    protected $fillable = ['nombre_genero', 'banner_opcional', 'color_primario', 'color_secundario', 'orden'];
    protected $appends = ['banner_url'];

    public function getBannerUrlAttribute()
    {
        return $this->banner_opcional ? '/storage/' . $this->banner_opcional : null;
    }

    public function temas()
    {
        return $this->hasMany(Tema::class, 'id_genero');
    }
}
