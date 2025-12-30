<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FinanzaController extends Controller
{
    public function configuracion()
    {
        return response()->json(\App\Models\ConfiguracionPagoBase::all());
    }

    public function liquidacion($id)
    {
        return response()->json(['liquidation' => 'calculated_data']);
    }

    public function miSueldo(Request $request)
    {
        return response()->json(['sueldo' => 1000]);
    }
}
