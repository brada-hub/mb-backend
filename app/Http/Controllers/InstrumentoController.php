<?php

namespace App\Http\Controllers;

use App\Models\Instrumento;
use Illuminate\Http\Request;

class InstrumentoController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'instrumento' => 'required|string|max:100',
            'id_seccion' => 'required|exists:secciones,id_seccion',
            'icon_slug' => 'nullable|string|max:50'
        ]);

        $nombre = mb_strtoupper($request->instrumento, 'UTF-8');

        $instrumento = Instrumento::create([
            'instrumento' => $nombre,
            'id_seccion' => $request->id_seccion,
            'icon_slug' => $request->icon_slug
        ]);

        return response()->json($instrumento, 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $instrumento = Instrumento::findOrFail($id);

        $request->validate([
            'instrumento' => 'required|string|max:100',
            'id_seccion' => 'required|exists:secciones,id_seccion',
            'icon_slug' => 'nullable|string|max:50'
        ]);

        $instrumento->update([
            'instrumento' => mb_strtoupper($request->instrumento, 'UTF-8'),
            'id_seccion' => $request->id_seccion,
            'icon_slug' => $request->icon_slug
        ]);

        return response()->json($instrumento);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $instrumento = Instrumento::findOrFail($id);

        // Verificar si tiene miembros asociados
        if ($instrumento->miembros()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar el instrumento porque tiene miembros asociados.'
            ], 422);
        }

        $instrumento->delete();

        return response()->json(['message' => 'Instrumento eliminado correctamente']);
    }
}
