<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Traits\BelongsToBanda;

class Rol extends Model
{
    use HasFactory, BelongsToBanda;
    protected $table = 'roles';
    protected $primaryKey = 'id_rol';
    protected $fillable = ['rol', 'descripcion', 'id_banda', 'es_protegido'];
    protected $casts = ['es_protegido' => 'boolean'];

    public function permisos()
    {
        return $this->belongsToMany(Permiso::class, 'rol_permiso', 'id_rol', 'id_permiso');
    }

    public function miembros()
    {
        return $this->hasMany(Miembro::class, 'id_rol', 'id_rol');
    }
}
