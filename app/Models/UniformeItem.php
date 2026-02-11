<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UniformeItem extends Model
{
    protected $fillable = ['uniforme_id', 'tipo', 'color', 'detalle'];

    public function uniforme()
    {
        return $this->belongsTo(Uniforme::class);
    }
}
