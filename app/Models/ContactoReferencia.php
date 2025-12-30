<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactoReferencia extends Model
{
    use HasFactory;
    protected $table = 'contactos_referencia';
    protected $primaryKey = 'id_contacto';
    protected $fillable = ['id_miembro', 'nombres_apellidos', 'parentesco', 'celular'];
}
