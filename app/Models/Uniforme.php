<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Uniforme extends Model
{
    protected $fillable = ['banda_id', 'nombre', 'descripcion'];

    public function items()
    {
        return $this->hasMany(UniformeItem::class);
    }

    public function banda()
    {
        return $this->belongsTo(Banda::class, 'banda_id', 'id_banda');
    }
}
