<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Traits\BelongsToBanda;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Formacion extends Model
{
    use HasFactory, BelongsToBanda;

    protected $table = 'formaciones';
    protected $primaryKey = 'id_formacion';
    protected $fillable = ['nombre', 'descripcion', 'id_banda', 'activo'];

    protected $casts = [
        'activo' => 'boolean'
    ];

    public function detalles(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DetalleFormacion::class, 'id_formacion');
    }

    public function miembros(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Miembro::class, 'detalle_formaciones', 'id_formacion', 'id_miembro');
    }
}
