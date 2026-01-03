<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_video';
    protected $fillable = ['url_video', 'titulo', 'id_tema'];

    public function tema()
    {
        return $this->belongsTo(Tema::class, 'id_tema');
    }
}
