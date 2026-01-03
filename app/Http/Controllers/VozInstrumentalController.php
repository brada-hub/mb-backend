<?php

namespace App\Http\Controllers;

use App\Models\VozInstrumental;
use Illuminate\Http\Request;

class VozInstrumentalController extends Controller
{
    public function index()
    {
        return VozInstrumental::all();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre_voz' => 'required|string|max:255|unique:voces_instrumentales,nombre_voz'
        ]);

        $voz = VozInstrumental::create([
            'nombre_voz' => mb_strtoupper($validated['nombre_voz'], 'UTF-8')
        ]);

        return response()->json($voz, 201);
    }

    public function destroy($id)
    {
        $voz = VozInstrumental::findOrFail($id);
        if ($voz->recursos()->count() > 0) {
            return response()->json(['message' => 'No se puede eliminar porque tiene recursos asociados.'], 422);
        }
        $voz->delete();
        return response()->json(['message' => 'Voz eliminada correctamente']);
    }
}
