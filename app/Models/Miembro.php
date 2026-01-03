<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Miembro extends Model
{
    use HasFactory;
    protected $table = 'miembros';
    protected $primaryKey = 'id_miembro';
    protected $fillable = [
        'id_categoria',
        'nombres',
        'apellidos',
        'ci',
        'celular',
        'fecha',
        'latitud',
        'longitud',
        'direccion',
        'id_seccion',
        'id_instrumento',
        'id_rol',
        'version_perfil' // Added this field based on user request schema
    ];

    public function user()
    {
        return $this->hasOne(User::class, 'id_miembro');
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'id_categoria');
    }

    public function seccion()
    {
        return $this->belongsTo(Seccion::class, 'id_seccion');
    }

    public function instrumento()
    {
        return $this->belongsTo(Instrumento::class, 'id_instrumento');
    }

    public function rol()
    {
        return $this->belongsTo(Rol::class, 'id_rol');
    }

    public function contactos()
    {
        return $this->hasMany(ContactoReferencia::class, 'id_miembro');
    }

    public function asistencia()
    {
        return $this->hasMany(Asistencia::class, 'id_miembro');
    }

    public function permisos()
    {
        return $this->belongsToMany(Permiso::class, 'miembro_permiso', 'id_miembro', 'id_permiso')
                    ->withPivot('estado_booleano')
                    ->withTimestamps();
    }
}
