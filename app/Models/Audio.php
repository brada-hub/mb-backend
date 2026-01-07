<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Audio extends Model
{
    use HasFactory;

    protected $table = 'audios';
    protected $primaryKey = 'id_audio';

    protected $fillable = [
        'url_audio',
        'tipo_entidad',
        'id_entidad'
    ];

    /**
     * Get the parent entity model (Tema or Mix).
     */
    public function entidad()
    {
        return $this->morphTo(__FUNCTION__, 'tipo_entidad', 'id_entidad');
    }
}
