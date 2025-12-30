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
        $data = $request->validate(['evento' => 'required', 'fecha' => 'required|date', 'id_tipo_evento' => 'required']);
        // Add full validation as needed
        return Evento::create($request->all());
    }
}
