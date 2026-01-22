<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Traits\BelongsToBanda;

class Mix extends Model
{
    use HasFactory, BelongsToBanda;

    protected $table = 'mixes';
    protected $primaryKey = 'id_mix';
    protected $fillable = ['nombre', 'activo', 'id_banda'];

    protected $casts = [
        'activo' => 'boolean'
    ];

    /**
     * Obtiene los temas que pertenecen al mix a través de detalle_mixes.
     */
    public function temas()
    {
        return $this->belongsToMany(Tema::class, 'detalle_mixes', 'id_mix', 'id_tema')
                    ->withPivot('id_detalle_mix', 'orden')
                    ->withTimestamps()
                    ->orderBy('detalle_mixes.orden');
    }

    /**
     * Obtiene los detalles del mix para un manejo más directo de los archivos.
     */
    public function detalles()
    {
        return $this->hasMany(DetalleMix::class, 'id_mix')->orderBy('orden');
    }

    public function audio()
    {
        return $this->morphOne(Audio::class, 'entidad', 'tipo_entidad', 'id_entidad');
    }
}
