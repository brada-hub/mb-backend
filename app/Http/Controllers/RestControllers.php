<?php

namespace App\Http\Controllers;

use App\Models\DispositivoAutorizado;
use Illuminate\Http\Request;

class RecursoController extends Controller {
    public function index() { return []; }
    public function store(Request $request) {}
}

class FinanzaController extends Controller {
    public function configuracion() {}
    public function liquidacion($id) {}
    public function miSueldo() {}
}

class NotificacionController extends Controller {
    public function index() {}
    public function leer($id) {}
}
