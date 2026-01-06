<?php

namespace App\Http\Controllers;

use App\Models\Tema;
use Illuminate\Http\Request;

class TemaController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $isMiembro = $user && $user->miembro && $user->miembro->rol && $user->miembro->rol->rol === 'MIEMBRO';
        $instrumentoId = $isMiembro ? $user->miembro->id_instrumento : null;

        $query = Tema::with(['genero', 'videos'])
            ->withCount([
                'recursos as recursos_count',
                'recursos as partituras_count' => function($q) use ($isMiembro, $instrumentoId) {
                    if ($isMiembro) {
                        $q->where('id_instrumento', $instrumentoId);
                    }
                    $q->whereHas('archivos', function($aq) {
                        $aq->whereIn('tipo', ['pdf', 'imagen']);
                    });
                },
                'recursos as guias_count' => function($q) use ($isMiembro, $instrumentoId) {
                    if ($isMiembro) {
                        $q->where('id_instrumento', $instrumentoId);
                    }
                    $q->whereHas('archivos', function($aq) {
                        $aq->where('tipo', 'audio');
                    });
                }
            ]);

        if ($request->has('id_genero')) {
            $query->where('id_genero', $request->id_genero);
        }

        if ($isMiembro && $instrumentoId) {
            $query->with(['recursos' => function($q) use ($instrumentoId) {
                $q->where('id_instrumento', $instrumentoId)
                  ->with(['archivos', 'voz']);
            }]);
        }

        return $query->get();
    }

    public function show($id)
    {
        return Tema::with(['genero', 'videos', 'recursos.archivos', 'recursos.voz', 'recursos.instrumento'])
            ->findOrFail($id);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_genero' => 'required|exists:generos,id_genero',
            'nombre_tema' => 'required|string|max:255',
            'url_video' => 'nullable|string'
        ]);

        $tema = Tema::create([
            'id_genero' => $validated['id_genero'],
            'nombre_tema' => mb_strtoupper($validated['nombre_tema'], 'UTF-8')
        ]);

        if (!empty($validated['url_video'])) {
            $tema->videos()->create([
                'url_video' => $validated['url_video'],
                'titulo' => 'Video Referencial'
            ]);
        }

        return response()->json($tema->load(['genero', 'videos']), 201);
    }

    public function update(Request $request, $id)
    {
        $tema = Tema::findOrFail($id);
        $validated = $request->validate([
            'id_genero' => 'nullable|exists:generos,id_genero',
            'nombre_tema' => 'nullable|string|max:255',
            'url_video' => 'nullable|string'
        ]);

        if (isset($validated['id_genero'])) {
            $tema->id_genero = $validated['id_genero'];
        }
        if (isset($validated['nombre_tema'])) {
            $tema->nombre_tema = mb_strtoupper($validated['nombre_tema'], 'UTF-8');
        }

        $tema->save();

        if (array_key_exists('url_video', $validated)) {
            if (empty($validated['url_video'])) {
                $tema->videos()->delete();
            } else {
                $video = $tema->videos()->first();
                if ($video) {
                    $video->update(['url_video' => $validated['url_video']]);
                } else {
                    $tema->videos()->create([
                        'url_video' => $validated['url_video'],
                        'titulo' => 'Video Referencial'
                    ]);
                }
            }
        }

        return response()->json($tema->load(['genero', 'videos']));
    }

    public function destroy($id)
    {
        $tema = Tema::findOrFail($id);
        if ($tema->recursos()->count() > 0) {
            return response()->json(['message' => 'No se puede eliminar el tema porque tiene recursos asociados.'], 422);
        }
        $tema->delete();
        return response()->json(['message' => 'Tema eliminado correctamente']);
    }
}
