<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguracionPagoBase extends Model
{
    use HasFactory;
    protected $table = 'configuracion_pagos_base';
    protected $primaryKey = 'id_pago_config';
    protected $fillable = ['id_categoria', 'id_seccion', 'monto_base_estandar', 'bono_instrumento'];
}
