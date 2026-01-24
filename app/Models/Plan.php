<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_plan';

    protected $fillable = [
        'nombre',
        'label',
        'max_miembros',
        'storage_mb',
        'can_upload_audio',
        'can_upload_video',
        'gps_attendance',
        'custom_branding',
        'precio_base',
        'features'
    ];

    protected $casts = [
        'can_upload_audio' => 'boolean',
        'can_upload_video' => 'boolean',
        'gps_attendance' => 'boolean',
        'custom_branding' => 'boolean',
        'features' => 'array',
        'precio_base' => 'float'
    ];

    public function bandas()
    {
        return $this->hasMany(Banda::class, 'id_plan');
    }
}
