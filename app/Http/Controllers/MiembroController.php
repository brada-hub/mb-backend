<?php

namespace App\Http\Controllers;

use App\Models\Miembro;
use Illuminate\Http\Request;

class MiembroController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Miembro::with(['categoria', 'seccion', 'rol'])->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_categoria' => 'required|exists:categorias,id_categoria',
            'nombres' => 'required|string|max:50',
            'apellidos' => 'required|string|max:50',
            'ci' => 'required|string|unique:miembros,ci',
            'celular' => 'required|integer',
            'fecha' => 'nullable|date',
            'id_seccion' => 'required|exists:secciones,id_seccion',
            'id_rol' => 'required|exists:roles,id_rol',
        ]);

        $miembro = Miembro::create($validated);
        return response()->json($miembro, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return Miembro::with(['user', 'contactos'])->findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $miembro = Miembro::findOrFail($id);

        $validated = $request->validate([
            'nombres' => 'string|max:50',
            'celular' => 'integer',
            'direccion' => 'nullable|string',
            'version_perfil' => 'integer'
        ]);

        $miembro->update($validated);
        return response()->json($miembro);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        Miembro::destroy($id);
        return response()->json(null, 204);
    }
}
