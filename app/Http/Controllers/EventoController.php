<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use Illuminate\Http\Request;

class EventoController extends Controller
{
    public function index() {
        return Evento::all();
    }

    public function proximos() {
        return Evento::where('fecha', '>=', now()->toDateString())
                     ->orderBy('fecha')
                     ->get();
    }

    public function store(Request $request) {
        $validated = $request->validate([
            'evento' => 'required|string',
            'fecha' => 'required|date',
            'id_tipo_evento' => 'required|integer'
        ]);

        $data = $request->all();
        $data['evento'] = mb_strtoupper($data['evento'], 'UTF-8');
        if (isset($data['descripcion'])) {
            $data['descripcion'] = mb_strtoupper($data['descripcion'], 'UTF-8');
        }

        return Evento::create($data);
    }
}
