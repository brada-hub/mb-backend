<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetalleMix extends Model
{
    use HasFactory;

    protected $table = 'detalle_mixes';
    protected $primaryKey = 'id_detalle_mix';
    protected $fillable = ['id_mix', 'id_tema', 'orden'];

    public function mix()
    {
        return $this->belongsTo(Mix::class, 'id_mix');
    }

    public function tema()
    {
        return $this->belongsTo(Tema::class, 'id_tema');
    }
}
