<?php

namespace App\Http\Controllers;

use App\Models\Genero;
use Illuminate\Http\Request;

class GeneroController extends Controller
{
    public function index()
    {
        return Genero::withCount('temas')->orderBy('orden')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre_genero' => 'required|string|max:255',
            'banner' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'color_primario' => 'nullable|string',
            'color_secundario' => 'nullable|string'
        ]);

        $bannerPath = null;
        if ($request->hasFile('banner')) {
            $bannerPath = $request->file('banner')->store('generos', 'public');
        }

        $genero = Genero::create([
            'nombre_genero' => mb_strtoupper($validated['nombre_genero'], 'UTF-8'),
            'banner_opcional' => $bannerPath,
            'color_primario' => $validated['color_primario'] ?? '#4f46e5',
            'color_secundario' => $validated['color_secundario'] ?? '#7c3aed'
        ]);

        return response()->json($genero, 201);
    }

    public function update(Request $request, $id)
    {
        $genero = Genero::findOrFail($id);
        $validated = $request->validate([
            'nombre_genero' => 'nullable|string|max:255',
            'banner' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'color_primario' => 'nullable|string',
            'color_secundario' => 'nullable|string'
        ]);

        if ($request->hasFile('banner')) {
            if ($genero->banner_opcional) {
                \Storage::disk('public')->delete($genero->banner_opcional);
            }
            $genero->banner_opcional = $request->file('banner')->store('generos', 'public');
        }

        if ($request->has('nombre_genero')) {
            $genero->nombre_genero = mb_strtoupper($request->nombre_genero, 'UTF-8');
        }

        if ($request->has('color_primario')) {
            $genero->color_primario = $request->color_primario;
        }
        if ($request->has('color_secundario')) {
            $genero->color_secundario = $request->color_secundario;
        }

        $genero->save();
        return response()->json($genero);
    }

    public function destroy($id)
    {
        $genero = Genero::findOrFail($id);
        if ($genero->temas()->count() > 0) {
            return response()->json(['message' => 'No se puede eliminar el género porque tiene temas asociados.'], 422);
        }
        $genero->delete();
        return response()->json(['message' => 'Género eliminado correctamente']);
    }

    public function reorder(Request $request)
    {
        $orders = $request->input('orders'); // Array of {id, orden}
        foreach ($orders as $item) {
            Genero::where('id_genero', $item['id'])->update(['orden' => $item['orden']]);
        }
        return response()->json(['message' => 'Orden actualizado correctamente']);
    }
}
