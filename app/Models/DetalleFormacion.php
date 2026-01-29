<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class DetalleFormacion extends Model
{
    use HasFactory;

    protected $table = 'detalle_formaciones';
    protected $primaryKey = 'id_detalle_formacion';
    protected $fillable = ['id_formacion', 'id_miembro'];

    public function formacion()
    {
        return $this->belongsTo(Formacion::class, 'id_formacion');
    }

    public function miembro()
    {
        return $this->belongsTo(Miembro::class, 'id_miembro');
    }
}
